<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Services\Catalog\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/** Semua perubahan status pesanan lewat sini agar aturannya konsisten. */
class OrderService
{
    public function __construct(private readonly StockService $stock) {}

    public function markPaid(Payment $payment, array $attributes = []): void
    {
        DB::transaction(function () use ($payment, $attributes) {
            $payment->fill($attributes + ['status' => PaymentStatus::Paid, 'paid_at' => now()])->save();

            $order = $payment->order()->lockForUpdate()->first();

            if ($order->status === OrderStatus::PendingPayment) {
                $order->update(['status' => OrderStatus::Paid, 'paid_at' => now()]);
            } elseif ($order->status === OrderStatus::Cancelled) {
                // Dana masuk untuk pesanan yang sudah batal: perlu refund manual oleh admin.
                Log::warning('Pembayaran diterima untuk pesanan yang sudah dibatalkan', ['order' => $order->code]);
            }
        });
    }

    public function markPaymentFailed(Payment $payment, PaymentStatus $status, array $attributes = []): void
    {
        DB::transaction(function () use ($payment, $status, $attributes) {
            $payment->fill($attributes + ['status' => $status])->save();

            $order = $payment->order()->lockForUpdate()->first();

            if ($order->status === OrderStatus::PendingPayment) {
                $this->cancel($order);
            }
        });
    }

    public function transition(Order $order, OrderStatus $status, ?string $trackingNumber = null): void
    {
        if (! $order->status->canTransitionTo($status)) {
            throw new InvalidArgumentException(
                "Status tidak bisa diubah dari {$order->status->label()} ke {$status->label()}."
            );
        }

        if ($status === OrderStatus::Cancelled) {
            $this->cancel($order);

            return;
        }

        $order->fill(['status' => $status]);

        match ($status) {
            // Konfirmasi manual oleh admin (mis. transfer langsung): tutup juga tagihan yang masih pending.
            OrderStatus::Paid => $this->settleManually($order),
            OrderStatus::Shipped => $order->fill(['shipped_at' => now(), 'tracking_number' => $trackingNumber]),
            OrderStatus::Completed => $order->completed_at = now(),
            default => null,
        };

        $order->save();
    }

    private function settleManually(Order $order): void
    {
        $order->paid_at = now();

        $order->payments()
            ->where('status', PaymentStatus::Pending)
            ->update(['status' => PaymentStatus::Paid, 'payment_type' => 'manual', 'paid_at' => now()]);
    }

    /** Batalkan pesanan dan kembalikan stok produk/varian. */
    public function cancel(Order $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order->items()->with('product', 'variant')->get() as $item) {
                $this->restock($order, $item);
            }

            $order->update(['status' => OrderStatus::Cancelled, 'cancelled_at' => now()]);
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
