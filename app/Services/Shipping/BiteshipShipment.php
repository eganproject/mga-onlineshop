<?php

namespace App\Services\Shipping;

use App\Enums\OrderStatus;
use App\Exceptions\ShipmentException;
use App\Models\Order;
use App\Services\Orders\OrderService;
use App\Services\Settings\StoreSettings;
use App\Support\ShippingConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Membuat pengiriman di Biteship dari pesanan yang sudah lunas
 * (POST /v1/orders): kurir dipesan, nomor resi terisi otomatis, pesanan jadi
 * "Dikirim". Setiap pengiriman memotong saldo Biteship, jadi hanya dijalankan
 * saat staf menekan tombol di detail pesanan, tidak pernah otomatis.
 *
 * Kolom orders.shipment_* tidak mass-assignable; diubah lewat forceFill di sini.
 */
class BiteshipShipment
{
    public const METHOD_PICKUP = 'pickup';

    public const METHOD_DROP_OFF = 'drop_off';

    /** Kode kurir RajaOngkir (pesanan lama) → kode Biteship. */
    private const ALIASES = ['ide' => 'idexpress', 'sentral' => 'sentralcargo'];

    /** Biteship: reference_id sudah pernah dipakai (pesanan ini sudah punya pengiriman). */
    private const DUPLICATE_REFERENCE = 40002060;

    private const FAILED_STATUSES = ['cancelled', 'rejected', 'courier_not_found'];

    public function __construct(
        private readonly OrderService $orders,
        private readonly IndonesiaRegions $regions,
    ) {}

    /** Status Biteship => [label, kelas badge]. */
    public static function statusLabel(?string $status): array
    {
        return match ($status) {
            'confirmed' => ['Dikonfirmasi', 'bg-sky-50 text-sky-700 ring-sky-600/20'],
            'scheduled' => ['Terjadwal', 'bg-sky-50 text-sky-700 ring-sky-600/20'],
            'allocated' => ['Kurir ditugaskan', 'bg-sky-50 text-sky-700 ring-sky-600/20'],
            'picking_up' => ['Kurir menjemput', 'bg-indigo-50 text-indigo-700 ring-indigo-600/20'],
            'picked' => ['Sudah dijemput', 'bg-indigo-50 text-indigo-700 ring-indigo-600/20'],
            'dropping_off' => ['Dalam pengiriman', 'bg-violet-50 text-violet-700 ring-violet-600/20'],
            'delivered' => ['Terkirim', 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'],
            'on_hold' => ['Ditahan', 'bg-amber-50 text-amber-700 ring-amber-600/20'],
            'return_in_transit' => ['Dikembalikan (dalam perjalanan)', 'bg-amber-50 text-amber-700 ring-amber-600/20'],
            'returned' => ['Dikembalikan', 'bg-rose-50 text-rose-700 ring-rose-600/20'],
            'rejected' => ['Ditolak', 'bg-rose-50 text-rose-700 ring-rose-600/20'],
            'courier_not_found' => ['Kurir tidak ditemukan', 'bg-rose-50 text-rose-700 ring-rose-600/20'],
            'cancelled' => ['Dibatalkan', 'bg-rose-50 text-rose-700 ring-rose-600/20'],
            'disposed' => ['Dimusnahkan', 'bg-rose-50 text-rose-700 ring-rose-600/20'],
            null, '' => ['Belum dibuat', 'bg-stone-100 text-stone-600 ring-stone-500/20'],
            default => [Str::headline($status), 'bg-stone-100 text-stone-600 ring-stone-500/20'],
        };
    }

    /** Kode kurir pesanan dalam kode Biteship, atau null bila bukan kurir Biteship. */
    public function courierCode(Order $order): ?string
    {
        $code = mb_strtolower(trim((string) $order->courier));
        $code = self::ALIASES[$code] ?? $code;

        return isset(StoreSettings::BITESHIP_COURIERS[$code]) || isset(StoreSettings::BITESHIP_INSTANT_COURIERS[$code]) ? $code : null;
    }

    public function isInstant(Order $order): bool
    {
        return isset(StoreSettings::BITESHIP_INSTANT_COURIERS[(string) $this->courierCode($order)]);
    }

    /**
     * Alasan pengiriman belum bisa dibuat untuk pesanan ini; null = siap.
     * Dipakai kartu "Pengiriman" untuk menampilkan tombol atau penjelasannya.
     */
    public function blocker(Order $order): ?string
    {
        return match (true) {
            ! ShippingConfig::isBiteship() => 'Penyedia ongkir aktif bukan Biteship.',
            ! ShippingConfig::hasApiKey() => 'API key Biteship belum diisi di Admin › Integrasi.',
            filled($order->shipment_id) && ! $this->canRebook($order) => 'Pengiriman sudah dibuat.',
            ! $order->paid_at => 'Pengiriman bisa dibuat setelah pesanan lunas.',
            ! in_array($order->status, [OrderStatus::Paid, OrderStatus::Processing], true) => 'Pesanan berstatus '.$order->status->label().'.',
            ! $this->courierCode($order) || blank($order->courier_service) => 'Kurir pesanan ini bukan kurir Biteship (ongkir manual atau ambil di toko).',
            blank(config('shop.address')) => 'Alamat gudang belum diisi di Pengaturan Toko.',
            blank(config('shop.whatsapp')) => 'Nomor WhatsApp toko belum diisi di Pengaturan Toko (dipakai sebagai kontak pengirim).',
            ! $this->originPostalCode() => 'Kecamatan asal Biteship belum dipilih di Pengaturan Toko.',
            $this->isInstant($order) && ! ShippingConfig::originCoordinates() => 'Kurir instan butuh pin lokasi gudang di Pengaturan Toko.',
            $this->isInstant($order) && ! $this->destinationCoordinates($order) => 'Kurir instan butuh pin lokasi pembeli, pesanan ini tidak menyimpannya.',
            ! $this->isInstant($order) && ! $this->destinationPostalCode($order) => 'Kode pos tujuan tidak diketahui. Pesan kurir lewat dashboard Biteship lalu isi resi manual.',
            default => null,
        };
    }

    /** Pengiriman sebelumnya gagal di Biteship (dibatalkan, ditolak, kurir tidak ada) dan paket belum jalan. */
    public function canRebook(Order $order): bool
    {
        return filled($order->shipment_id)
            && in_array($order->shipment_status, self::FAILED_STATUSES, true)
            && in_array($order->status, [OrderStatus::Paid, OrderStatus::Processing], true);
    }

    /**
     * Pesan kurir di Biteship. Bila resi langsung terbit, pesanan ditandai Dikirim.
     *
     * @throws ShipmentException
     */
    public function create(Order $order, string $method = self::METHOD_PICKUP): void
    {
        if ($blocker = $this->blocker($order)) {
            throw new ShipmentException($blocker);
        }

        $instant = $this->isInstant($order);
        $rebook = $this->canRebook($order);
        $response = $this->request(fn (PendingRequest $http) => $http->post('/v1/orders', array_filter([
            'shipper_contact_name' => config('shop.full_name'),
            'shipper_contact_phone' => config('shop.whatsapp'),
            'shipper_contact_email' => config('shop.email'),
            'shipper_organization' => config('shop.full_name'),
            'origin_contact_name' => config('shop.full_name'),
            'origin_contact_phone' => config('shop.whatsapp'),
            'origin_address' => config('shop.address'),
            'origin_postal_code' => (int) $this->originPostalCode(),
            'origin_coordinate' => $this->coordinate(ShippingConfig::originCoordinates()),
            'origin_collection_method' => $instant || $method !== self::METHOD_DROP_OFF ? self::METHOD_PICKUP : self::METHOD_DROP_OFF,
            'destination_contact_name' => $order->recipient_name,
            'destination_contact_phone' => $order->recipient_phone,
            'destination_contact_email' => $order->customerEmail(),
            'destination_address' => $order->shipping_address,
            'destination_postal_code' => ($postal = $this->destinationPostalCode($order)) ? (int) $postal : null,
            'destination_coordinate' => $this->coordinate($this->destinationCoordinates($order)),
            'courier_company' => $this->courierCode($order),
            'courier_type' => mb_strtolower((string) $order->courier_service),
            'delivery_type' => 'now',
            'order_note' => filled($order->notes) ? Str::limit($order->notes, 180, '') : null,
            // reference_id harus unik di Biteship; pemesanan ulang memakai akhiran waktu.
            'reference_id' => $rebook ? $order->code.'-'.now()->format('ymdHis') : $order->code,
            // Satu baris dengan berat total, sama seperti saat ongkir dihitung di checkout.
            'items' => [[
                'name' => "Pesanan {$order->code}",
                'description' => Str::limit($order->items->map(fn ($item) => "{$item->quantity}x {$item->product_name}")->implode(', '), 180),
                'value' => max(1, (int) $order->subtotal),
                'quantity' => 1,
                'weight' => max(1, (int) $order->total_weight),
            ]],
        ], fn ($value) => $value !== null && $value !== '')));

        if ($response->failed() || $response->json('success') === false) {
            // Sudah pernah dibuat (mis. respons pertama hilang): pakai pengiriman yang ada.
            if ((int) $response->json('code') === self::DUPLICATE_REFERENCE && filled($response->json('details.order_id'))) {
                $this->store($order, [
                    'id' => $response->json('details.order_id'),
                    'courier' => ['waybill_id' => $response->json('details.waybill_id')],
                ], $method);
                $this->refresh($order);

                return;
            }

            throw new ShipmentException($this->errorMessage($response));
        }

        if ($rebook) {
            $order->forceFill(['shipment_tracking_id' => null, 'shipment_link' => null, 'shipment_booked_at' => null])->saveQuietly();
        }

        $this->store($order, (array) $response->json(), $method);
    }

    /**
     * Ambil status & resi terbaru dari Biteship (GET /v1/orders/{id}).
     *
     * @throws ShipmentException
     */
    public function refresh(Order $order): void
    {
        if (blank($order->shipment_id)) {
            throw new ShipmentException('Pesanan ini belum punya pengiriman Biteship.');
        }

        $response = $this->request(fn (PendingRequest $http) => $http->get('/v1/orders/'.rawurlencode($order->shipment_id)));

        if ($response->failed() || $response->json('success') === false) {
            throw new ShipmentException($this->errorMessage($response));
        }

        $this->store($order, (array) $response->json(), $order->shipment_method);
    }

    /** Simpan data pengiriman dan tandai pesanan Dikirim begitu resinya ada. */
    private function store(Order $order, array $data, ?string $method): void
    {
        $courier = (array) ($data['courier'] ?? []);
        $waybill = filled($courier['waybill_id'] ?? null) ? trim((string) $courier['waybill_id']) : null;

        $order->forceFill(array_filter([
            'shipment_id' => $data['id'] ?? $order->shipment_id,
            'shipment_tracking_id' => $courier['tracking_id'] ?? null,
            'shipment_status' => $data['status'] ?? null,
            'shipment_method' => $method,
            'shipment_price' => is_numeric($data['price'] ?? null) ? (int) $data['price'] : null,
            'shipment_link' => filled($courier['link'] ?? null) && Str::startsWith((string) $courier['link'], 'https://') ? Str::limit((string) $courier['link'], 500, '') : null,
            'shipment_booked_at' => $order->shipment_booked_at ?? now(),
        ], fn ($value) => $value !== null))->saveQuietly();

        if (! $waybill) {
            return;
        }

        $note = 'Pengiriman dibuat lewat Biteship, nomor resi '.$waybill.'.';

        if ($order->status === OrderStatus::Paid) {
            $this->orders->transition($order, OrderStatus::Processing);
        }

        if ($order->status === OrderStatus::Processing) {
            $this->orders->transition($order, OrderStatus::Shipped, $waybill, $note);
        } elseif ($order->status->isTrackable() && $order->tracking_number !== $waybill && blank($order->tracking_number)) {
            $this->orders->updateTracking($order, $waybill);
        }
    }

    public function originPostalCode(): ?string
    {
        return ShippingConfig::origin('postal_code')
            ?? (ShippingConfig::origin('district_id') ? $this->regions->postalCode(ShippingConfig::origin('district_id')) : null);
    }

    /** Kode pos tujuan: tersimpan di pesanan, dari kecamatan, atau 5 digit di akhir alamat. */
    public function destinationPostalCode(Order $order): ?string
    {
        if (preg_match('/^\d{5}$/', (string) $order->destination_postal_code)) {
            return $order->destination_postal_code;
        }

        if ($order->destination_id && ($postal = $this->regions->postalCode((string) $order->destination_id))) {
            return $postal;
        }

        return preg_match('/\b(\d{5})\s*$/', (string) $order->shipping_address, $m) ? $m[1] : null;
    }

    /** @return array{0: float, 1: float}|null */
    public function destinationCoordinates(Order $order): ?array
    {
        return ShippingConfig::coordinates($order->destination_latitude, $order->destination_longitude);
    }

    private function coordinate(?array $point): ?array
    {
        return $point ? ['latitude' => $point[0], 'longitude' => $point[1]] : null;
    }

    private function request(callable $send): Response
    {
        try {
            return $send(Http::baseUrl((string) config('services.biteship.base_url'))
                ->withHeaders(['Authorization' => (string) config('services.biteship.api_key')])
                ->acceptJson()
                ->asJson()
                // Tanpa retry: permintaan ulang bisa memesan kurir dua kali.
                ->timeout(30));
        } catch (ConnectionException) {
            throw new ShipmentException('Server tidak bisa terhubung ke Biteship. Cek di dashboard Biteship apakah pengiriman sudah tercatat sebelum mencoba lagi.');
        }
    }

    private function errorMessage(Response $response): string
    {
        $error = $response->json('error') ?? $response->json('message');
        $error = is_string($error) && $error !== '' ? Str::limit($error, 200) : 'HTTP '.$response->status();

        return match (true) {
            in_array($response->status(), [401, 403], true) => 'API key Biteship ditolak: '.$error,
            Str::contains(mb_strtolower($error), ['balance', 'saldo']) => 'Saldo Biteship tidak cukup: '.$error,
            default => 'Biteship menolak pengiriman: '.$error,
        };
    }
}
