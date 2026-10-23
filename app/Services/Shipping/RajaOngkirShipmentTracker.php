<?php

namespace App\Services\Shipping;

use App\Contracts\ShipmentTracker;
use App\Data\TrackingEvent;
use App\Data\TrackingResult;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Lacak resi lewat RajaOngkir (Komerce): POST /track/waybill?awb=..&courier=..
 * Kurir JNE mewajibkan 5 digit terakhir nomor HP penerima (last_phone_number).
 */
class RajaOngkirShipmentTracker implements ShipmentTracker
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl,
    ) {}

    public function track(Order $order): TrackingResult
    {
        if (! $this->apiKey) {
            throw new RuntimeException('RAJAONGKIR_API_KEY belum diatur.');
        }

        if (! $order->tracking_number || ! $order->courier) {
            throw new RuntimeException('Pesanan belum punya kurir atau nomor resi.');
        }

        $query = array_filter([
            'awb' => $order->tracking_number,
            'courier' => mb_strtolower($order->courier),
            'last_phone_number' => mb_strtolower($order->courier) === 'jne' ? $order->recipientPhoneLastDigits() : null,
        ]);

        $data = Http::baseUrl($this->baseUrl)
            ->withHeaders(['key' => $this->apiKey])
            ->acceptJson()
            ->timeout(15)
            ->retry(2, 300, fn ($e) => $e instanceof ConnectionException, throw: false)
            ->post('/track/waybill?'.http_build_query($query))
            ->throw()
            ->json('data');

        if (! is_array($data)) {
            throw new RuntimeException('Respons lacak resi tidak dikenali.');
        }

        return self::parse($data);
    }

    /** Ubah data mentah API jadi TrackingResult. Dipisah agar mudah dites. */
    public static function parse(array $data): TrackingResult
    {
        $summary = (array) ($data['summary'] ?? []);
        $delivery = (array) ($data['delivery_status'] ?? []);

        $events = collect($data['manifest'] ?? [])
            ->filter(fn ($row) => is_array($row) && filled($row['manifest_description'] ?? null))
            ->map(fn (array $row) => new TrackingEvent(
                at: self::date($row['manifest_date'] ?? null, $row['manifest_time'] ?? null),
                description: trim((string) $row['manifest_description']),
                location: filled($row['city_name'] ?? null) ? trim((string) $row['city_name']) : null,
            ))
            ->sortByDesc(fn (TrackingEvent $e) => $e->at?->getTimestamp() ?? 0)
            ->values()
            ->all();

        $status = $delivery['status'] ?? $summary['status'] ?? null;
        $delivered = filter_var($data['delivered'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || mb_strtoupper((string) $status) === 'DELIVERED';

        return new TrackingResult(
            delivered: $delivered,
            status: filled($status) ? (string) $status : null,
            events: $events,
            receiver: filled($delivery['pod_receiver'] ?? null) ? (string) $delivery['pod_receiver'] : null,
            deliveredAt: $delivered ? self::date($delivery['pod_date'] ?? null, $delivery['pod_time'] ?? null) : null,
            checkedAt: CarbonImmutable::now(),
        );
    }

    private static function date(?string $date, ?string $time): ?CarbonImmutable
    {
        if (blank($date)) {
            return null;
        }

        try {
            return CarbonImmutable::parse(trim($date.' '.$time), config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }
}
