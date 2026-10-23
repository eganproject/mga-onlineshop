<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Shipping\TrackingService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Cek resi pesanan yang sedang dikirim; yang sudah diterima ditandai "Sampai Tujuan". */
#[Signature('orders:sync-tracking {--limit=100 : Maksimal pesanan yang dicek sekali jalan}')]
#[Description('Perbarui status pesanan dikirim berdasarkan pelacakan resi kurir')]
class SyncOrderTracking extends Command
{
    public function handle(TrackingService $tracking): int
    {
        $checked = 0;
        $delivered = 0;

        Order::where('status', OrderStatus::Shipped)
            ->whereNotNull('tracking_number')
            ->oldest('shipped_at')
            ->limit((int) $this->option('limit'))
            ->get()
            ->each(function (Order $order) use ($tracking, &$checked, &$delivered) {
                $tracking->track($order, fresh: true);
                $checked++;

                if ($order->status === OrderStatus::Delivered) {
                    $delivered++;
                }
            });

        $this->info("{$checked} resi dicek, {$delivered} pesanan sampai tujuan.");

        return self::SUCCESS;
    }
}
