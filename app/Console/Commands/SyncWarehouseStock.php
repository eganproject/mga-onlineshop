<?php

namespace App\Console\Commands;

use App\Models\WarehouseSyncRun;
use App\Services\Warehouse\WarehouseApiException;
use App\Services\Warehouse\WarehouseStockSync;
use Illuminate\Console\Command;

/** Tarik stok dari warehouse29 (dijadwalkan di routes/console.php). */
class SyncWarehouseStock extends Command
{
    protected $signature = 'warehouse:sync-stock {--full : Tarik semua SKU, bukan hanya yang berubah sejak tarikan terakhir}';

    protected $description = 'Sinkron stok produk dari API stok warehouse29';

    public function handle(WarehouseStockSync $sync): int
    {
        try {
            $run = $sync->run((bool) $this->option('full'));
        } catch (WarehouseApiException $e) {
            $this->warn($e->getMessage());

            return self::SUCCESS;
        }

        if ($run->status !== WarehouseSyncRun::STATUS_SUCCESS) {
            $this->error('Sinkron gagal: '.$run->message);

            return self::FAILURE;
        }

        $this->info("Sinkron {$run->modeLabel()} selesai: {$run->fetched} SKU diterima, {$run->updated} stok diubah, {$run->unmatched} SKU gudang belum terhubung.");

        return self::SUCCESS;
    }
}
