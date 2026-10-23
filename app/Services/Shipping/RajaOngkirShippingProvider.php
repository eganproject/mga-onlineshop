<?php

namespace App\Services\Shipping;

use App\Contracts\ShippingProvider;
use App\Data\Region;
use App\Data\ShippingRate;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Integrasi RajaOngkir versi Komerce (https://rajaongkir.komerce.id).
 *
 * Wilayah: GET /destination/province, /destination/city/{province},
 * /destination/district/{city}. Ongkir: POST /calculate/district/domestic-cost
 * (asal & tujuan = ID kecamatan). Data wilayah jarang berubah, jadi disimpan
 * di cache 30 hari agar hemat kuota API.
 */
class RajaOngkirShippingProvider implements ShippingProvider
{
    private const REGION_TTL_DAYS = 30;

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl,
        private readonly ?string $originId,
        private readonly array $couriers,
    ) {}

    public function provinces(): array
    {
        return $this->regions('provinces', '/destination/province');
    }

    public function cities(string $provinceId): array
    {
        return $this->regions("cities:{$provinceId}", '/destination/city/'.rawurlencode($provinceId));
    }

    public function districts(string $cityId): array
    {
        return $this->regions("districts:{$cityId}", '/destination/district/'.rawurlencode($cityId));
    }

    public function rates(string $districtId, int $weight): array
    {
        if (! $this->originId) {
            throw new RuntimeException('SHOP_ORIGIN_DISTRICT_ID belum diatur.');
        }

        $data = $this->client()
            ->asForm()
            ->post('/calculate/district/domestic-cost', [
                'origin' => $this->originId,
                'destination' => $districtId,
                'weight' => max(1, $weight),
                'courier' => implode(':', $this->couriers),
                'price' => 'lowest',
            ])
            ->throw()
            ->json('data', []);

        return collect($data)
            ->filter(fn ($row) => is_array($row) && isset($row['code'], $row['service'], $row['cost']))
            ->map(fn (array $row) => new ShippingRate(
                courier: mb_strtolower($row['code']),
                courierName: $row['name'] ?? mb_strtoupper($row['code']),
                service: (string) $row['service'],
                description: (string) ($row['description'] ?? ''),
                cost: (int) $row['cost'],
                etd: $this->formatEtd($row['etd'] ?? null),
            ))
            ->values()
            ->all();
    }

    /** @return list<Region> */
    private function regions(string $cacheKey, string $path): array
    {
        $key = 'rajaongkir:'.$cacheKey;

        if (is_array($cached = Cache::get($key))) {
            return $this->toRegions($cached);
        }

        $rows = $this->client()->get($path)->throw()->json('data', []);
        $rows = is_array($rows) ? array_values(array_filter($rows, fn ($row) => is_array($row) && isset($row['id'], $row['name']))) : [];

        // Hasil kosong tidak disimpan, supaya bisa dicoba lagi.
        if ($rows !== []) {
            Cache::put($key, $rows, now()->addDays(self::REGION_TTL_DAYS));
        }

        return $this->toRegions($rows);
    }

    /** @return list<Region> */
    private function toRegions(array $rows): array
    {
        return collect($rows)
            ->map(fn (array $row) => new Region(
                id: (string) $row['id'],
                name: self::displayName((string) $row['name']),
                postalCode: filled($row['zip_code'] ?? null) && $row['zip_code'] !== '0' ? (string) $row['zip_code'] : null,
            ))
            ->sortBy('name')
            ->values()
            ->all();
    }

    /** "JAKARTA SELATAN" → "Jakarta Selatan", dengan singkatan seperti DKI/DI tetap kapital. */
    public static function displayName(string $name): string
    {
        $title = Str::title(mb_strtolower(trim($name)));

        return preg_replace_callback('/\b(Dki|Di|Nad|Ntb|Ntt)\b/u', fn ($m) => mb_strtoupper($m[1]), $title);
    }

    private function formatEtd(mixed $etd): ?string
    {
        $etd = trim(str_ireplace(['days', 'day', 'hari'], '', (string) $etd));

        return $etd === '' ? null : $etd.' hari';
    }

    private function client(): PendingRequest
    {
        if (! $this->apiKey) {
            throw new RuntimeException('RAJAONGKIR_API_KEY belum diatur.');
        }

        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['key' => $this->apiKey])
            ->acceptJson()
            ->timeout(15)
            // Ulangi hanya bila koneksi putus, bukan saat API menolak permintaan.
            ->retry(2, 300, fn ($e) => $e instanceof ConnectionException, throw: false);
    }
}
