<?php

namespace App\Observers;

use App\Mail\OrderPlacedMail;
use App\Mail\OrderStatusMail;
use App\Models\Order;
use Illuminate\Support\Facades\Mail;

/**
 * Kirim email ke pelanggan saat pesanan dibuat atau statusnya berubah.
 * Email masuk antrean dan baru diproses setelah transaksi database selesai.
 */
class OrderObserver
{
    public function created(Order $order): void
    {
        if ($user = $order->user) {
            Mail::to($user)->queue(new OrderPlacedMail($order));
        }
    }

    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status') || ! in_array($order->status, OrderStatusMail::STATUSES, true)) {
            return;
        }

        if ($user = $order->user) {
            Mail::to($user)->queue(new OrderStatusMail($order, $order->status));
        }
    }
}
