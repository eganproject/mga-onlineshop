<?php

namespace App\Observers;

use App\Mail\OrderPlacedMail;
use App\Models\Order;
use Illuminate\Support\Facades\Mail;

/**
 * Kirim email ke pelanggan saat pesanan dibuat. Email perubahan status
 * dikirim oleh listener SendOrderStatusMail (event OrderStatusChanged).
 */
class OrderObserver
{
    public function created(Order $order): void
    {
        if ($user = $order->user) {
            Mail::to($user)->queue(new OrderPlacedMail($order));
        }
    }
}
