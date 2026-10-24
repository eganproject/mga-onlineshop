<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Data\PaymentUpdate;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentUnavailableException;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Orders\OrderService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Alur pembayaran pesanan: membuat/membuat ulang tagihan, menerapkan status
 * dari gateway secara idempoten, membatalkan, dan mengakhiri pesanan yang
 * lewat batas bayar. Driver gateway hanya dipakai untuk bicara dengan API.
 */
class PaymentService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly OrderService $orders,
    ) {}

    public function gateway(): PaymentGateway
    {
        return $this->gateway;
    }

    /** Tagihan pending yang masih bisa langsung dibayar dengan driver aktif. */
    public function activePayment(Order $order): ?Payment
    {
        if ($order->status !== OrderStatus::PendingPayment) {
            return null;
        }

        return $order->payments()
            ->where('gateway', $this->gateway->name())
            ->where('status', PaymentStatus::Pending)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('id')
            ->first();
    }

    /** Masih boleh membuat tagihan baru (pesanan belum dibayar & belum lewat batas). */
    public function canCreatePayment(Order $order): bool
    {
        return $order->status === OrderStatus::PendingPayment && $order->paymentDeadline()->isFuture();
    }

    /** Buat tagihan pertama saat checkout. */
    public function createFor(Order $order): Payment
    {
        return $this->gateway->createPayment($order, $order->paymentDeadline());
    }

    /**
     * Tagihan untuk halaman pembayaran: pakai yang aktif, atau buat baru bila
     * belum ada / sebelumnya gagal / kedaluwarsa.
     *
     * @throws PaymentUnavailableException
     */
    public function preparePayment(Order $order): Payment
    {
        return $this->withOrderLock($order, function () use ($order) {
            if ($payment = $this->activePayment($order)) {
                return $payment;
            }

            $this->ensureCanCreate($order);

            return $this->createFor($order);
        });
    }

    /**
     * Buat link pembayaran baru (mis. ingin ganti metode bayar). Tagihan
     * pending sebelumnya dicek dulu ke gateway, lalu dibatalkan.
     *
     * @throws PaymentUnavailableException
     */
    public function regenerate(Order $order): Payment
    {
        return $this->withOrderLock($order, function () use ($order) {
            $this->syncPending($order);
            $order->refresh();

            $this->ensureCanCreate($order);
            $this->voidPendingPayments($order);

            return $this->createFor($order);
        });
    }

    /**
     * Terapkan status dari gateway. Aman dipanggil berulang dan tidak
     * berurutan: perubahan yang tidak masuk akal diabaikan.
     * Mengembalikan null bila tagihan tidak dikenal.
     */
    public function apply(PaymentUpdate $update): ?Payment
    {
        $payment = $this->findPayment($update->externalId);

        if (! $payment) {
            return null;
        }

        DB::transaction(function () use ($payment, $update) {
            $payment = Payment::query()->lockForUpdate()->find($payment->id);

            if ($update->amount !== null && $update->amount !== $payment->amount) {
                Log::warning('Nominal notifikasi pembayaran tidak sesuai tagihan', [
                    'payment' => $payment->id, 'expected' => $payment->amount, 'received' => $update->amount,
                ]);

                return;
            }

            $attributes = array_filter([
                'reference' => $update->reference,
                'payment_type' => $update->paymentType,
            ]) + ['payload' => $update->payload ?: $payment->payload];

            if ($update->status === PaymentStatus::Paid && $payment->status === PaymentStatus::Paid && $payment->payment_type === 'manual') {
                // Sudah dikonfirmasi manual oleh admin, tapi pelanggan tetap membayar lewat gateway.
                Log::warning('Pembayaran gateway masuk untuk pesanan yang sudah dikonfirmasi manual, cek kemungkinan bayar ganda', [
                    'order' => $payment->order->code, 'payment' => $payment->id,
                ]);
            }

            if (! $update->status || ! $payment->status->canMoveTo($update->status)) {
                // Notifikasi ganda / terlambat. Selama masih pending, catat metode yang dipilih.
                if ($payment->status === PaymentStatus::Pending) {
                    $payment->fill($attributes)->save();
                }

                return;
            }

            if ($update->status === PaymentStatus::Paid) {
                $this->orders->markPaid($payment, $attributes);

                return;
            }

            $payment->fill($attributes + ['status' => $update->status])->save();

            if ($update->status === PaymentStatus::Refunded) {
                Log::warning('Pembayaran dikembalikan (refund) di gateway', ['order' => $payment->order->code]);
            }

            // Gagal/dibatalkan: pesanan tetap menunggu, pelanggan bisa buat link baru.
            // Kedaluwarsa setelah batas bayar: pesanan dibatalkan dan stok kembali.
            if ($update->status === PaymentStatus::Expired) {
                $this->expireOrderIfOverdue($payment->order);
            }
        });

        return $payment->refresh();
    }

    /** Cek status tagihan langsung ke gateway (dipakai bila webhook terlambat). */
    public function sync(Payment $payment): Payment
    {
        if ($payment->gateway !== $this->gateway->name()) {
            return $payment;
        }

        $update = $this->gateway->fetchStatus($payment);

        return $update ? ($this->apply($update) ?? $payment) : $payment;
    }

    /** Cek semua tagihan pending pesanan ke gateway. */
    public function syncPending(Order $order): void
    {
        $order->payments()
            ->where('gateway', $this->gateway->name())
            ->where('status', PaymentStatus::Pending)
            ->get()
            ->each(fn (Payment $payment) => $this->sync($payment));
    }

    /**
     * Pembatalan oleh pelanggan. Status dicek ke gateway dulu agar pesanan
     * yang ternyata sudah dibayar tidak ikut batal. False bila tidak jadi batal.
     */
    public function cancelByCustomer(Order $order, ?string $reason = null): bool
    {
        $this->syncPending($order);

        return DB::transaction(function () use ($order, $reason) {
            $locked = Order::query()->lockForUpdate()->find($order->id);

            if ($locked->status !== OrderStatus::PendingPayment) {
                return false;
            }

            // Stok dikembalikan; tagihan pending dibatalkan lewat voidPendingPayments().
            $this->orders->cancel($locked, $reason);

            return true;
        });
    }

    /**
     * Batalkan pesanan yang lewat batas bayar dan kembalikan stoknya.
     * Dijalankan terjadwal (shop:expire-payments). Mengembalikan jumlah pesanan.
     */
    public function expireOverdueOrders(): int
    {
        $count = 0;
        $fallback = now()->subHours((int) config('shop.payment.expiry_hours'));

        Order::query()
            ->where('status', OrderStatus::PendingPayment)
            ->where(fn ($q) => $q->where('payment_due_at', '<=', now())
                ->orWhere(fn ($q) => $q->whereNull('payment_due_at')->where('created_at', '<=', $fallback)))
            ->chunkById(100, function ($orders) use (&$count) {
                foreach ($orders as $order) {
                    try {
                        // Jangan batalkan pesanan yang sebenarnya sudah dibayar tapi webhook-nya belum masuk.
                        $this->syncPending($order);
                    } catch (Throwable $e) {
                        report($e);

                        continue;
                    }

                    $count += (int) $this->expireOrderIfOverdue($order);
                }
            });

        return $count;
    }

    /**
     * Tandai tagihan pending sebagai dibatalkan dan batalkan juga di gateway.
     * Dipanggil otomatis setiap pesanan berubah menjadi Dibatalkan.
     */
    public function voidPendingPayments(Order $order): void
    {
        $payments = $order->payments()->where('status', PaymentStatus::Pending)->get();

        foreach ($payments as $payment) {
            $updated = Payment::query()
                ->whereKey($payment->id)
                ->where('status', PaymentStatus::Pending)
                ->update(['status' => PaymentStatus::Cancelled, 'updated_at' => now()]);

            if (! $updated || $payment->gateway !== $this->gateway->name()) {
                continue;
            }

            try {
                $this->gateway->cancel($payment);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    private function expireOrderIfOverdue(Order $order): bool
    {
        return DB::transaction(function () use ($order) {
            $locked = Order::query()->lockForUpdate()->find($order->id);

            // Toleransi 1 menit: kedaluwarsa di gateway dibulatkan ke menit.
            if ($locked->status !== OrderStatus::PendingPayment || $locked->paymentDeadline()->isAfter(now()->addMinute())) {
                return false;
            }

            $locked->payments()
                ->where('status', PaymentStatus::Pending)
                ->update(['status' => PaymentStatus::Expired, 'updated_at' => now()]);

            $this->orders->cancel($locked, 'Pembayaran kedaluwarsa.');

            return true;
        });
    }

    private function findPayment(string $externalId): ?Payment
    {
        $payment = Payment::query()
            ->where('gateway', $this->gateway->name())
            ->where('external_id', $externalId)
            ->first();

        if ($payment) {
            return $payment;
        }

        // Tagihan lama (sebelum ada external_id) memakai kode pesanan sebagai order_id.
        return Payment::query()
            ->where('gateway', $this->gateway->name())
            ->whereNull('external_id')
            ->whereHas('order', fn ($q) => $q->where('code', $externalId))
            ->latest('id')
            ->first();
    }

    private function ensureCanCreate(Order $order): void
    {
        if ($order->status !== OrderStatus::PendingPayment) {
            throw new PaymentUnavailableException('Pesanan ini sudah tidak menunggu pembayaran.');
        }

        if (! $this->canCreatePayment($order)) {
            throw new PaymentUnavailableException('Batas waktu pembayaran pesanan ini sudah lewat.');
        }
    }

    /** Cegah dua tab membuat dua tagihan sekaligus untuk pesanan yang sama. */
    private function withOrderLock(Order $order, callable $callback): mixed
    {
        return Cache::lock('payment:order:'.$order->id, 20)->block(10, $callback);
    }
}
