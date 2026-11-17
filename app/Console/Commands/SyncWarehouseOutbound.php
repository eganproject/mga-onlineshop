<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Warehouse\WarehouseOutbound;
use Illuminate\Console\Command;
use Throwable;

/** Cek status persetujuan outbound manual di warehouse29 (pending → approved). */
class SyncWarehouseOutbound extends Command
{
    protected $signature = 'warehouse:sync-outbound';

    protected $description = 'Perbarui status outbound penjualan manual dari warehouse29';

    public function handle(WarehouseOutbound $outbound): int
    {
        if (! $outbound->enabled()) {
            $this->info('Kirim penjualan manual ke gudang tidak aktif.');

            return self::SUCCESS;
        }

        $updated = 0;

        Order::query()
            ->where('warehouse_outbound_status', Order::OUTBOUND_PENDING)
            ->where('warehouse_outbound_sent_at', '>=', now()->subDays(60))
            ->orderBy('id')
            ->limit(40) // API gudang 60 permintaan/menit, dipakai bersama sinkron stok.
            ->get()
            ->each(function (Order $order) use ($outbound, &$updated) {
                try {
                    $updated += (int) ($outbound->refresh($order) !== Order::OUTBOUND_PENDING);
                } catch (Throwable $e) {
                    report($e);
                }
            });

        $this->info("{$updated} outbound berubah status.");

        return self::SUCCESS;
    }
}
