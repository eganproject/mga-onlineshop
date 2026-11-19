<?php

namespace App\Services\Shipping;

use App\Contracts\ShippingProvider;
use App\Data\ShippingRate;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Integrasi Biteship (https://biteship.com/id/docs).
 *
 * Biteship tidak menyediakan daftar provinsi/kota/kecamatan, jadi wilayah
 * diambil dari data bawaan (IndonesiaRegions). Ongkir: POST /v1/rates/couriers
 * dengan kode pos asal & tujuan. Kode pos tujuan = kode pos kecamatan yang
 * dipilih; untuk alamat lama (ID wilayah RajaOngkir) dipakai kode pos yang
 * tertulis di alamatnya.
 */
class BiteshipShippingProvider implements ShippingProvider
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl,
        private readonly ?string $originDistrictId,
        private readonly ?string $originPostalCode,
        private readonly array $couriers,
        private readonly IndonesiaRegions $regions = new IndonesiaRegions,
    ) {}

    public function provinces(): array
    {
        return $this->regions->provinces();
    }

    public function cities(string $provinceId): array
    {
        return $this->regions->cities($provinceId);
    }

    public function districts(string $cityId): array
    {
        return $this->regions->districts($cityId);
    }

    public function rates(string $districtId, int $weight, ?string $postalCode = null): array
    {
        $origin = $this->postal($this->originPostalCode) ?? ($this->originDistrictId ? $this->regions->postalCode($this->originDistrictId) : null);

        if (! $origin) {
            throw new RuntimeException('Kecamatan asal pengiriman untuk Biteship belum diatur.');
        }

        $destination = $this->regions->postalCode($districtId) ?? $this->postal($postalCode);

        if (! $destination) {
            throw new RuntimeException("Kode pos tujuan tidak diketahui (kecamatan {$districtId}).");
        }

        $pricing = $this->client()
            ->asJson()
            ->post('/v1/rates/couriers', [
                'origin_postal_code' => (int) $origin,
                'destination_postal_code' => (int) $destination,
                'couriers' => implode(',', $this->couriers),
                // Biteship mewajibkan rincian barang; ongkir cukup dihitung dari berat total.
                'items' => [[
                    'name' => 'Paket',
                    'value' => 10000,
                    'weight' => max(1, $weight),
                    'quantity' => 1,
                ]],
            ])
            ->throw()
            ->json('pricing', []);

        return collect(is_array($pricing) ? $pricing : [])
            ->filter(fn ($row) => is_array($row) && isset($row['courier_code'], $row['courier_service_code'], $row['price']))
            // Kurir instan/sameday butuh titik koordinat, tidak cocok untuk tarif per kode pos.
            ->reject(fn (array $row) => in_array($row['service_type'] ?? null, ['instant', 'same_day'], true))
            ->map(fn (array $row) => new ShippingRate(
                courier: mb_strtolower((string) $row['courier_code']),
                courierName: (string) ($row['courier_name'] ?? mb_strtoupper((string) $row['courier_code'])),
                service: mb_strtoupper((string) $row['courier_service_code']),
                description: (string) ($row['courier_service_name'] ?? $row['description'] ?? ''),
                cost: (int) $row['price'],
                etd: $this->formatEtd($row),
            ))
            ->filter(fn (ShippingRate $rate) => $rate->cost > 0)
            ->unique(fn (ShippingRate $rate) => $rate->key())
            ->values()
            ->all();
    }

    /** "2 - 3" + "days" → "2-3 hari". */
    private function formatEtd(array $row): ?string
    {
        $range = preg_replace('/\s+/', '', (string) ($row['shipment_duration_range'] ?? ''));

        if ($range === '') {
            return null;
        }

        return $range.(($row['shipment_duration_unit'] ?? 'days') === 'hours' ? ' jam' : ' hari');
    }

    private function postal(?string $value): ?string
    {
        return preg_match('/^\d{5}$/', trim((string) $value)) ? trim((string) $value) : null;
    }

    private function client(): PendingRequest
    {
        if (! $this->apiKey) {
            throw new RuntimeException('BITESHIP_API_KEY belum diatur.');
        }

        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['Authorization' => $this->apiKey])
            ->acceptJson()
            ->timeout(15)
            ->retry(2, 300, fn ($e) => $e instanceof ConnectionException, throw: false);
    }
}
