<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Warehouse\WarehouseApiException;
use App\Services\Warehouse\WarehouseOutbound;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

/** Kirim pesanan lunas (website & manual) ke warehouse29 (Outbound Manual), dengan retry bertahap. */
class SendWarehouseOutbound implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @return list<int> jeda antar percobaan (detik) */
    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function __construct(public Order $order) {}

    public function handle(WarehouseOutbound $outbound): void
    {
        $order = $this->order->fresh();

        if (! $order || ! $outbound->shouldSend($order)) {
            return;
        }

        // Koneksi gagal / 5xx: dilempar supaya dicoba lagi (ref_no sama, aman dari dobel).
        $outbound->send($order);
    }

    public function failed(?Throwable $e): void
    {
        $this->order->fresh()?->forceFill([
            'warehouse_outbound_status' => Order::OUTBOUND_FAILED,
            'warehouse_outbound_error' => Str::limit($e instanceof WarehouseApiException ? $e->getMessage() : 'Gagal mengirim ke gudang. Coba kirim ulang.', 500),
        ])->saveQuietly();
    }
}
