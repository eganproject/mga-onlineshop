<?php

namespace App\Services\Shipping;

use App\Contracts\ShipmentTracker;
use App\Data\TrackingResult;
use App\Models\Order;
use App\Services\Orders\OrderService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lapisan di atas ShipmentTracker: cache hasil lacak (hemat kuota API),
 * menelan error API, dan menandai pesanan "Sampai Tujuan" otomatis bila
 * kurir melaporkan paket sudah diterima.
 */
class TrackingService
{
    public function __construct(
        private readonly ShipmentTracker $tracker,
        private readonly OrderService $orders,
    ) {}

    public function canTrack(Order $order): bool
    {
        return $order->status->isTrackable() && filled($order->tracking_number) && filled($order->courier);
    }

    /** Null bila belum bisa dilacak atau API sedang bermasalah. */
    public function track(Order $order, bool $fresh = false): ?TrackingResult
    {
        if (! $this->canTrack($order)) {
            return null;
        }

        $key = $this->cacheKey($order);

        if (! $fresh && is_array($cached = Cache::get($key))) {
            $result = TrackingResult::fromArray($cached);
        } else {
            try {
                $result = $this->tracker->track($order);
            } catch (Throwable $e) {
                Log::warning('Lacak resi gagal', ['order' => $order->code, 'error' => $e->getMessage()]);

                return null;
            }

            Cache::put($key, $result->toArray(), now()->addMinutes(config('shop.shipping.tracking_cache_minutes', 60)));
        }

        if ($result->delivered) {
            $this->orders->markDelivered($order, $result->deliveredAt, $this->deliveredNote($result));
        }

        return $result;
    }

    public function forget(Order $order): void
    {
        Cache::forget($this->cacheKey($order));
    }

    private function deliveredNote(TrackingResult $result): string
    {
        return 'Kurir melaporkan paket sudah diterima'.($result->receiver ? " oleh {$result->receiver}" : '').'.';
    }

    private function cacheKey(Order $order): string
    {
        return 'tracking:'.$order->id.':'.md5($order->courier.'|'.$order->tracking_number);
    }
}
