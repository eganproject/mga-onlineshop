<?php

namespace App\Services\Shipping;

use App\Contracts\ShippingProvider;
use App\Data\Region;
use App\Data\ShippingRate;
use App\Exceptions\ShippingUnavailableException;
use App\Models\Address;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lapisan di atas ShippingProvider: menyimpan hasil ongkir di cache (hemat
 * kuota & harga konsisten antara halaman checkout dan saat pesanan dibuat),
 * mengurutkan tarif, dan mengubah error API jadi pesan yang ramah.
 */
class ShippingService
{
    public function __construct(private readonly ShippingProvider $provider) {}

    /** @return list<Region> */
    public function provinces(): array
    {
        return $this->guard(fn () => $this->provider->provinces(), 'Data provinsi');
    }

    /** @return list<Region> */
    public function cities(string $provinceId): array
    {
        return $this->guard(fn () => $this->provider->cities($provinceId), 'Data kota');
    }

    /** @return list<Region> */
    public function districts(string $cityId): array
    {
        return $this->guard(fn () => $this->provider->districts($cityId), 'Data kecamatan');
    }

    /**
     * Pastikan kombinasi provinsi/kota/kecamatan valid dan ambil namanya dari
     * penyedia (nama dari form tidak dipercaya). Null bila tidak cocok.
     *
     * @return array{province: Region, city: Region, district: Region}|null
     */
    public function resolveRegion(string $provinceId, string $cityId, string $districtId): ?array
    {
        $province = $this->pick($this->provinces(), $provinceId);
        $city = $province ? $this->pick($this->cities($provinceId), $cityId) : null;
        $district = $city ? $this->pick($this->districts($cityId), $districtId) : null;

        return $district ? compact('province', 'city', 'district') : null;
    }

    /**
     * Daftar ongkir ke alamat, termurah dulu.
     *
     * @return list<ShippingRate>
     *
     * @throws ShippingUnavailableException
     */
    public function rates(Address $address, int $weight): array
    {
        if (! $address->district_id) {
            throw new ShippingUnavailableException('Alamat ini belum lengkap. Edit alamat dan pilih provinsi, kota, dan kecamatan.');
        }

        $weight = max(1, $weight);
        $key = 'shipping:rates:'.md5(implode('|', [
            config('shop.shipping.driver'),
            config('shop.shipping.origin_district_id'),
            implode(':', config('shop.shipping.couriers')),
            $address->district_id,
            $weight,
        ]));

        // Disimpan sebagai array biasa: cache Laravel menolak unserialize objek.
        $cached = Cache::get($key);

        if (is_array($cached)) {
            $rates = array_map(ShippingRate::fromArray(...), $cached);
        } else {
            $rates = $this->guard(fn () => $this->provider->rates($address->district_id, $weight), 'Ongkos kirim');
            usort($rates, fn (ShippingRate $a, ShippingRate $b) => $a->cost <=> $b->cost);

            if ($rates !== []) {
                Cache::put($key, array_map(fn (ShippingRate $rate) => $rate->toArray(), $rates), now()->addMinutes((int) config('shop.shipping.cache_minutes', 360)));
            }
        }

        if ($rates === []) {
            throw new ShippingUnavailableException('Belum ada kurir yang melayani alamat ini. Coba alamat lain atau hubungi kami.');
        }

        return $rates;
    }

    /** Cari tarif berdasarkan kunci pilihan (contoh "jne|REG"). */
    public function findRate(Address $address, int $weight, string $key): ?ShippingRate
    {
        return collect($this->rates($address, $weight))->first(fn (ShippingRate $rate) => $rate->key() === $key);
    }

    /** @param list<Region> $regions */
    private function pick(array $regions, string $id): ?Region
    {
        return collect($regions)->first(fn (Region $region) => $region->id === $id);
    }

    private function guard(callable $callback, string $what): array
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            Log::warning("{$what} gagal diambil dari penyedia ongkir", ['error' => $e->getMessage()]);

            throw new ShippingUnavailableException("{$what} sedang tidak bisa dimuat. Coba beberapa saat lagi.", previous: $e);
        }
    }
}
