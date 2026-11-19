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
 * Lacak resi lewat Biteship (public tracking):
 * GET /v1/trackings/{nomor resi}/couriers/{kode kurir}.
 */
class BiteshipShipmentTracker implements ShipmentTracker
{
    /** Kode kurir RajaOngkir (pesanan lama) → kode Biteship. */
    private const ALIASES = ['ide' => 'idexpress', 'sentral' => 'sentralcargo'];

    /** Status Biteship → keterangan untuk riwayat bila kurir tidak memberi catatan. */
    private const STATUS_LABELS = [
        'confirmed' => 'Pesanan dikonfirmasi kurir',
        'allocated' => 'Kurir sudah ditugaskan',
        'picking_up' => 'Kurir menuju lokasi penjemputan',
        'picked' => 'Paket sudah dijemput kurir',
        'dropping_off' => 'Paket dalam perjalanan ke tujuan',
        'on_hold' => 'Pengiriman ditahan sementara',
        'return_in_transit' => 'Paket dalam perjalanan kembali ke pengirim',
        'returned' => 'Paket dikembalikan ke pengirim',
        'delivered' => 'Paket sudah diterima',
        'rejected' => 'Pengiriman ditolak',
        'courier_not_found' => 'Kurir tidak ditemukan',
        'disposed' => 'Paket dimusnahkan',
        'cancelled' => 'Pengiriman dibatalkan',
    ];

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl,
    ) {}

    public function track(Order $order): TrackingResult
    {
        if (! $this->apiKey) {
            throw new RuntimeException('BITESHIP_API_KEY belum diatur.');
        }

        if (! $order->tracking_number || ! $order->courier) {
            throw new RuntimeException('Pesanan belum punya kurir atau nomor resi.');
        }

        $courier = mb_strtolower(trim($order->courier));
        $courier = self::ALIASES[$courier] ?? $courier;

        $data = Http::baseUrl($this->baseUrl)
            ->withHeaders(['Authorization' => $this->apiKey])
            ->acceptJson()
            ->timeout(15)
            ->retry(2, 300, fn ($e) => $e instanceof ConnectionException, throw: false)
            ->get('/v1/trackings/'.rawurlencode(trim($order->tracking_number)).'/couriers/'.rawurlencode($courier))
            ->throw()
            ->json();

        if (! is_array($data) || ($data['success'] ?? true) === false) {
            throw new RuntimeException('Respons lacak resi Biteship tidak dikenali.');
        }

        return self::parse($data);
    }

    /** Ubah data mentah API jadi TrackingResult. Dipisah agar mudah dites. */
    public static function parse(array $data): TrackingResult
    {
        $events = collect($data['history'] ?? [])
            ->filter(fn ($row) => is_array($row) && (filled($row['note'] ?? null) || filled($row['status'] ?? null)))
            ->map(fn (array $row) => new TrackingEvent(
                at: self::date($row['updated_at'] ?? null),
                description: filled($row['note'] ?? null)
                    ? trim((string) $row['note'])
                    : (self::STATUS_LABELS[$row['status']] ?? (string) $row['status']),
            ))
            ->sortByDesc(fn (TrackingEvent $e) => $e->at?->getTimestamp() ?? 0)
            ->values()
            ->all();

        $status = mb_strtolower((string) ($data['status'] ?? ''));
        $delivered = $status === 'delivered';
        $deliveredRow = collect($data['history'] ?? [])->last(fn ($row) => is_array($row) && ($row['status'] ?? null) === 'delivered');

        return new TrackingResult(
            delivered: $delivered,
            status: $status !== '' ? (self::STATUS_LABELS[$status] ?? $status) : null,
            events: $events,
            receiver: null,
            deliveredAt: $delivered ? self::date($deliveredRow['updated_at'] ?? null) : null,
            checkedAt: CarbonImmutable::now(),
        );
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value)->setTimezone(config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }
}
