<?php

namespace App\Services\Orders;

use App\Contracts\PaymentGateway;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Services\Catalog\StockService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/** Semua perubahan status pesanan lewat sini agar aturannya konsisten. */
class OrderService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * Tandai tagihan lunas dan pesanan Dibayar. Idempoten: notifikasi ganda
     * tidak menggeser paid_at. Dipanggil dari PaymentService.
     */
    public function markPaid(Payment $payment, array $attributes = []): void
    {
        DB::transaction(function () use ($payment, $attributes) {
            $payment->fill($attributes + ['status' => PaymentStatus::Paid]);
            $payment->paid_at ??= now();
            $payment->save();

            $order = $payment->order()->lockForUpdate()->first();

            if ($order->status === OrderStatus::PendingPayment) {
                $order->update([
                    'status' => OrderStatus::Paid,
                    'paid_at' => $payment->paid_at,
                    'payment_method' => $payment->method ?: $payment->payment_type,
                    'payment_fee' => $payment->fee,
                    'gateway_fee' => $payment->gateway_fee,
                ]);
            } elseif ($order->status === OrderStatus::Cancelled) {
                // Dana masuk untuk pesanan yang sudah batal: perlu refund manual oleh admin.
                Log::warning('Pembayaran diterima untuk pesanan yang sudah dibatalkan', ['order' => $order->code]);
            }
        });
    }

    public function transition(Order $order, OrderStatus $status, ?string $trackingNumber = null, ?string $note = null): void
    {
        DB::transaction(fn () => $this->applyTransition($this->lockFresh($order), $status, $trackingNumber, $note));
    }

    private function applyTransition(Order $order, OrderStatus $status, ?string $trackingNumber, ?string $note): void
    {
        if (! $order->status->canTransitionTo($status)) {
            throw new InvalidArgumentException(
                "Status tidak bisa diubah dari {$order->status->label()} ke {$status->label()}."
            );
        }

        if ($status === OrderStatus::Cancelled) {
            $this->cancel($order, $note);

            return;
        }

        $order->fill(['status' => $status])->withStatusNote($note);

        match ($status) {
            // Konfirmasi manual oleh admin (mis. transfer langsung): tutup juga tagihan yang masih pending.
            OrderStatus::Paid => $this->settleManually($order),
            OrderStatus::Shipped => $this->fillShipped($order, $trackingNumber, $note),
            OrderStatus::Delivered => $order->delivered_at ??= now(),
            OrderStatus::Completed => $this->fillCompleted($order),
            default => null,
        };

        $order->save();
    }

    /** Ubah nomor resi setelah pesanan dikirim (mis. salah ketik). */
    public function updateTracking(Order $order, string $trackingNumber): void
    {
        if (! $order->status->isTrackable()) {
            throw new InvalidArgumentException('Nomor resi hanya bisa diubah setelah pesanan dikirim.');
        }

        $old = $order->tracking_number;

        if ($old === $trackingNumber) {
            return;
        }

        $order->update(['tracking_number' => $trackingNumber]);
        $order->addHistoryNote($old ? "Nomor resi diubah dari {$old} menjadi {$trackingNumber}." : "Nomor resi diisi: {$trackingNumber}.");
    }

    /** Paket sampai tujuan (dari admin atau hasil pelacakan kurir). */
    public function markDelivered(Order $order, ?CarbonInterface $at = null, ?string $note = null): bool
    {
        return DB::transaction(function () use ($order, $at, $note) {
            // Dicek ulang di dalam kunci: pelacakan bisa berjalan lama dan
            // pelanggan mungkin sudah menekan "Pesanan diterima".
            if ($this->lockFresh($order)->status !== OrderStatus::Shipped) {
                return false;
            }

            $order->withStatusNote($note)->update([
                'status' => OrderStatus::Delivered,
                'delivered_at' => $at && $at->isPast() ? $at : now(),
            ]);

            return true;
        });
    }

    /** Pelanggan menekan "Pesanan diterima", atau pesanan selesai otomatis. */
    public function complete(Order $order, ?string $note = null): void
    {
        DB::transaction(function () use ($order, $note) {
            if (! $this->lockFresh($order)->status->canBeConfirmedByCustomer()) {
                throw new InvalidArgumentException('Pesanan ini belum bisa diselesaikan.');
            }

            $order->fill(['status' => OrderStatus::Completed])->withStatusNote($note);
            $this->fillCompleted($order);
            $order->save();
        });
    }

    /**
     * Kunci baris pesanan dan samakan isi objek dengan data terbaru di
     * database, supaya keputusan status tidak memakai data basi.
     */
    private function lockFresh(Order $order): Order
    {
        $fresh = Order::query()->lockForUpdate()->findOrFail($order->getKey());
        $order->setRawAttributes($fresh->getAttributes(), sync: true);

        return $order;
    }

    private function fillShipped(Order $order, ?string $trackingNumber, ?string $note): void
    {
        $order->fill(['shipped_at' => now(), 'tracking_number' => $trackingNumber ?: $order->tracking_number]);

        $courier = $order->courierLabel() ? ' '.$order->courierLabel() : '';
        $resi = $order->tracking_number ? ", nomor resi {$order->tracking_number}" : '';

        $order->withStatusNote($note ?? "Paket diserahkan ke kurir{$courier}{$resi}.");
    }

    private function fillCompleted(Order $order): void
    {
        $order->completed_at = now();
        $order->delivered_at ??= now();
    }

    private function settleManually(Order $order): void
    {
        // Dibayar di luar gateway (tunai/transfer langsung): tanpa biaya transaksi & potongan Midtrans.
        $order->forceFill(['paid_at' => now(), 'payment_method' => 'manual', 'payment_fee' => 0, 'gateway_fee' => 0]);

        $pending = $order->payments()->where('status', PaymentStatus::Pending)->get();

        $order->payments()
            ->whereKey($pending->modelKeys())
            ->update(['status' => PaymentStatus::Paid, 'payment_type' => 'manual', 'method' => 'manual', 'gateway_fee' => 0, 'paid_at' => now()]);

        // Link bayar di gateway ditutup supaya pelanggan tidak membayar dua kali.
        DB::afterCommit(function () use ($pending) {
            $gateway = app(PaymentGateway::class);

            foreach ($pending->where('gateway', $gateway->name()) as $payment) {
                try {
                    $gateway->cancel($payment);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        });
    }

    /**
     * Batalkan pesanan dan kembalikan stok produk/varian. Aman dipanggil dua
     * kali: pesanan yang sudah batal tidak di-restock ulang.
     */
    public function cancel(Order $order, ?string $reason = null): void
    {
        DB::transaction(function () use ($order, $reason) {
            if ($this->lockFresh($order)->status === OrderStatus::Cancelled) {
                return;
            }

            foreach ($order->items()->with('product', 'variant')->get() as $item) {
                $this->restock($order, $item);
            }

            $reason = filled($reason) ? Str::limit(trim($reason), 250) : null;

            $order->withStatusNote($reason)->update([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);
        });
    }

    private function restock(Order $order, OrderItem $item): void
    {
        $product = $item->product;

        if (! $product) {
            return; // Produk sudah dihapus, tidak ada stok yang perlu dikembalikan.
        }

        if ($item->variant) {
            $this->stock->adjust($product, $item->variant, $item->quantity, StockMovementType::Cancellation, order: $order);
        } elseif (! $item->product_variant_id && ! $product->has_variants) {
            $this->stock->adjust($product, null, $item->quantity, StockMovementType::Cancellation, order: $order);
        } else {
            // Varian sudah dihapus atau struktur varian berubah: admin perlu menyesuaikan manual.
            Log::info('Stok tidak dikembalikan otomatis karena varian berubah', ['order' => $order->code, 'item' => $item->id]);
        }
    }
}
