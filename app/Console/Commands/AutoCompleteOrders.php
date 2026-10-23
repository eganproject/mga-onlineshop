<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Orders\OrderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Selesaikan pesanan yang sudah sampai tapi tidak dikonfirmasi pelanggan,
 * serta pesanan "Dikirim" yang sudah terlalu lama (batas aman).
 */
#[Signature('orders:auto-complete')]
#[Description('Selesaikan otomatis pesanan yang sudah sampai tujuan')]
class AutoCompleteOrders extends Command
{
    public function handle(OrderService $orders): int
    {
        $count = 0;
        $deliveredDays = config('shop.orders.auto_complete_days');
        $shippedDays = config('shop.orders.shipped_auto_complete_days');

        if ($deliveredDays > 0) {
            Order::where('status', OrderStatus::Delivered)
                ->where('delivered_at', '<=', now()->subDays($deliveredDays))
                ->lazyById(100)->each(function (Order $order) use ($orders, $deliveredDays, &$count) {
                    $orders->complete($order, "Selesai otomatis {$deliveredDays} hari setelah paket sampai.");
                    $count++;
                });
        }

        if ($shippedDays > 0) {
            Order::where('status', OrderStatus::Shipped)
                ->where('shipped_at', '<=', now()->subDays($shippedDays))
                ->lazyById(100)->each(function (Order $order) use ($orders, $shippedDays, &$count) {
                    $orders->complete($order, "Selesai otomatis {$shippedDays} hari setelah dikirim.");
                    $count++;
                });
        }

        $this->info("{$count} pesanan diselesaikan otomatis.");

        return self::SUCCESS;
    }
}
