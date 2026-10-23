<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Mail\OrderStatusMail;
use Illuminate\Support\Facades\Mail;

/** Email ke pelanggan saat status pesanan berubah ke status penting. */
class SendOrderStatusMail
{
    public function handle(OrderStatusChanged $event): void
    {
        // Pesanan baru sudah dapat email "pesanan diterima" dari OrderObserver.
        if ($event->from === null || ! in_array($event->to, OrderStatusMail::STATUSES, true)) {
            return;
        }

        if ($user = $event->order->user) {
            Mail::to($user)->queue(new OrderStatusMail($event->order, $event->to));
        }
    }
}
