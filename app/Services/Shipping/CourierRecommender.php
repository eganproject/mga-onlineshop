<?php

namespace App\Services\Shipping;

use App\Contracts\ShippingProvider;
use App\Data\Region;
use App\Data\ShippingRate;
use App\Exceptions\ShippingUnavailableException;
use App\Services\Settings\StoreSettings;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rekomendasi kurir untuk Admin › Pengaturan Toko.
 *
 * Mengecek semua kurir yang didukung RajaOngkir dari kecamatan asal ke dua
 * tujuan contoh (satu kecamatan dengan gudang, dan satu kota besar di provinsi
 * lain). Kurir yang mengembalikan tarif ke semua tujuan = direkomendasikan.
 * Hasil disimpan 12 jam supaya hemat kuota API.
 */
class CourierRecommender
{
    private const CACHE_HOURS = 12;

    private const WEIGHT = 1000;

    /** Kota pembanding di luar provinsi asal: [provinsi, kota], dipakai yang pertama cocok. */
    private const REFERENCES = [
        ['jawa timur', 'surabaya'],
        ['dki jakarta', 'jakarta pusat'],
        ['jawa barat', 'bandung'],
    ];

    public function __construct(private readonly ShippingProvider $provider) {}

    public static function available(): bool
    {
        return config('shop.shipping.driver') === 'rajaongkir' && filled(config('services.rajaongkir.api_key'));
    }

    /**
     * @return array{recommended: list<string>, couriers: array<string, array{available: bool, reach: int, services: int, cheapest: ?int, etd: ?string}>, destinations: list<string>, checked_at: string}
     *
     * @throws ShippingUnavailableException
     */
    public function recommend(string $originId, bool $fresh = false, ?string $provinceId = null): array
    {
        if (! self::available() || ! $this->provider instanceof RajaOngkirShippingProvider) {
            throw new ShippingUnavailableException('Rekomendasi butuh RajaOngkir aktif. Isi API key & pilih driver RajaOngkir di Admin › Integrasi.');
        }

        $key = 'shipping:courier-recommendation:'.$originId;

        if (! $fresh && is_array($cached = Cache::get($key))) {
            return $cached;
        }

        try {
            $destinations = $this->destinations($originId, $provinceId ?: (string) config('shop.shipping.origin_province_id'));
            $couriers = array_keys(StoreSettings::COURIERS);
            $results = [];

            foreach ($destinations as $id => $label) {
                $results[$label] = $this->quote($originId, $id, $couriers);
            }
        } catch (ShippingUnavailableException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('Rekomendasi kurir gagal diambil dari RajaOngkir', ['error' => $e->getMessage()]);

            throw new ShippingUnavailableException('RajaOngkir sedang tidak bisa dihubungi. Cek API key di Admin › Integrasi atau coba lagi nanti.', previous: $e);
        }

        $result = $this->summarize($couriers, $results);
        Cache::put($key, $result, now()->addHours(self::CACHE_HOURS));

        return $result;
    }

    /**
     * @param  list<string>  $couriers
     * @param  array<string, list<ShippingRate>>  $results
     */
    private function summarize(array $couriers, array $results): array
    {
        $summary = [];

        foreach ($couriers as $code) {
            $rates = [];
            $reach = 0;

            foreach ($results as $destinationRates) {
                $own = array_filter($destinationRates, fn (ShippingRate $rate) => $rate->courier === $code && $rate->cost > 0);
                $reach += $own === [] ? 0 : 1;
                $rates = [...$rates, ...$own];
            }

            usort($rates, fn (ShippingRate $a, ShippingRate $b) => $a->cost <=> $b->cost);

            $summary[$code] = [
                'available' => $reach > 0,
                'reach' => $reach,
                'services' => count(array_unique(array_map(fn (ShippingRate $rate) => $rate->service, $rates))),
                'cheapest' => $rates[0]->cost ?? null,
                'etd' => $rates[0]->etd ?? null,
            ];
        }

        $total = count($results);
        $recommended = array_keys(array_filter($summary, fn (array $row) => $row['reach'] === $total));

        // Tidak ada yang menjangkau semua tujuan → pakai yang menjangkau sebagian.
        if ($recommended === []) {
            $recommended = array_keys(array_filter($summary, fn (array $row) => $row['available']));
        }

        return [
            'recommended' => $recommended,
            'couriers' => $summary,
            'destinations' => array_keys($results),
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Tujuan contoh: kecamatan asal sendiri + satu kota besar di provinsi lain.
     *
     * @return array<string, string> id kecamatan => label
     */
    private function destinations(string $originId, string $originProvince): array
    {
        $destinations = [$originId => 'sekitar gudang'];
        $provinces = $this->provider->provinces();

        foreach (self::REFERENCES as [$provinceName, $cityName]) {
            $province = $this->find($provinces, $provinceName);

            if (! $province || $province->id === $originProvince) {
                continue;
            }

            $city = $this->find($this->provider->cities($province->id), $cityName);
            $district = $city ? ($this->provider->districts($city->id)[0] ?? null) : null;

            if ($district && $district->id !== $originId) {
                $destinations[$district->id] = $city->name;
                break;
            }
        }

        return $destinations;
    }

    /** @param  list<Region>  $regions */
    private function find(array $regions, string $name): ?Region
    {
        $match = fn (Region $region, bool $exact) => $exact
            ? mb_strtolower($region->name) === $name
            : str_contains(mb_strtolower($region->name), $name);

        return collect($regions)->first(fn (Region $r) => $match($r, true))
            ?? collect($regions)->first(fn (Region $r) => $match($r, false) && ! str_contains(mb_strtolower($r->name), 'kabupaten'));
    }

    /**
     * Satu permintaan untuk semua kurir. Bila API menolak (mis. satu kode kurir
     * tidak dikenal), ulangi per kurir supaya kurir lain tetap terbaca.
     *
     * @param  list<string>  $couriers
     * @return list<ShippingRate>
     */
    private function quote(string $origin, string $destination, array $couriers): array
    {
        /** @var RajaOngkirShippingProvider $provider */
        $provider = $this->provider;

        try {
            return $provider->quote($origin, $destination, self::WEIGHT, $couriers);
        } catch (RequestException $e) {
            if ($e->response->status() === 404) {
                return [];
            }

            if (! in_array($e->response->status(), [400, 422], true) || count($couriers) === 1) {
                throw $e;
            }
        }

        $rates = [];

        foreach ($couriers as $courier) {
            try {
                $rates = [...$rates, ...$provider->quote($origin, $destination, self::WEIGHT, [$courier])];
            } catch (RequestException $e) {
                if (! in_array($e->response->status(), [400, 404, 422], true)) {
                    throw $e;
                }
            }
        }

        return $rates;
    }
}
