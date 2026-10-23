<?php

namespace App\Services\Shipping;

use App\Contracts\ShippingProvider;
use App\Data\Region;
use App\Data\ShippingRate;

/**
 * Driver ongkir untuk development lokal tanpa API key. Berisi contoh data
 * wilayah (sebagian kota besar) dan tarif yang dihitung dari berat serta
 * jarak kasar (satu kota / satu provinsi / satu pulau / beda pulau).
 */
class FakeShippingProvider implements ShippingProvider
{
    /** Kecamatan asal bawaan bila SHOP_ORIGIN_DISTRICT_ID kosong: Kebayoran Baru. */
    public const DEFAULT_ORIGIN = '10101';

    /**
     * [id provinsi => [nama, pulau, [id kota => [nama, [id kecamatan => [nama, kode pos]]]]]]
     */
    private const REGIONS = [
        '1' => ['DKI Jakarta', 'jawa', [
            '101' => ['Kota Jakarta Selatan', ['10101' => ['Kebayoran Baru', '12110'], '10102' => ['Tebet', '12810'], '10103' => ['Pasar Minggu', '12510']]],
            '102' => ['Kota Jakarta Barat', ['10201' => ['Grogol Petamburan', '11450'], '10202' => ['Kebon Jeruk', '11530']]],
            '103' => ['Kota Jakarta Timur', ['10301' => ['Cakung', '13910'], '10302' => ['Duren Sawit', '13440']]],
        ]],
        '2' => ['Jawa Barat', 'jawa', [
            '201' => ['Kota Bandung', ['20101' => ['Coblong', '40132'], '20102' => ['Sukajadi', '40162'], '20103' => ['Lengkong', '40262']]],
            '202' => ['Kota Bekasi', ['20201' => ['Bekasi Selatan', '17141'], '20202' => ['Bekasi Barat', '17134']]],
            '203' => ['Kota Bogor', ['20301' => ['Bogor Tengah', '16121'], '20302' => ['Bogor Utara', '16153']]],
            '204' => ['Kota Depok', ['20401' => ['Beji', '16421'], '20402' => ['Pancoran Mas', '16431']]],
        ]],
        '3' => ['Banten', 'jawa', [
            '301' => ['Kota Tangerang', ['30101' => ['Cipondoh', '15148'], '30102' => ['Karawaci', '15115']]],
            '302' => ['Kota Tangerang Selatan', ['30201' => ['Serpong', '15310'], '30202' => ['Pondok Aren', '15224']]],
        ]],
        '4' => ['Jawa Tengah', 'jawa', [
            '401' => ['Kota Semarang', ['40101' => ['Semarang Tengah', '50132'], '40102' => ['Tembalang', '50275']]],
            '402' => ['Kota Surakarta', ['40201' => ['Laweyan', '57141'], '40202' => ['Banjarsari', '57131']]],
        ]],
        '5' => ['DI Yogyakarta', 'jawa', [
            '501' => ['Kota Yogyakarta', ['50101' => ['Gondokusuman', '55221'], '50102' => ['Umbulharjo', '55161']]],
            '502' => ['Kabupaten Sleman', ['50201' => ['Depok', '55281'], '50202' => ['Mlati', '55284']]],
        ]],
        '6' => ['Jawa Timur', 'jawa', [
            '601' => ['Kota Surabaya', ['60101' => ['Gubeng', '60281'], '60102' => ['Wonokromo', '60243'], '60103' => ['Sukolilo', '60111']]],
            '602' => ['Kota Malang', ['60201' => ['Lowokwaru', '65141'], '60202' => ['Klojen', '65111']]],
        ]],
        '7' => ['Bali', 'bali-nusra', [
            '701' => ['Kota Denpasar', ['70101' => ['Denpasar Selatan', '80222'], '70102' => ['Denpasar Barat', '80119']]],
            '702' => ['Kabupaten Badung', ['70201' => ['Kuta', '80361'], '70202' => ['Kuta Utara', '80361']]],
        ]],
        '8' => ['Sumatera Utara', 'sumatera', [
            '801' => ['Kota Medan', ['80101' => ['Medan Baru', '20153'], '80102' => ['Medan Petisah', '20112']]],
        ]],
        '9' => ['Sulawesi Selatan', 'sulawesi', [
            '901' => ['Kota Makassar', ['90101' => ['Panakkukang', '90231'], '90102' => ['Rappocini', '90222']]],
        ]],
        '10' => ['Kalimantan Timur', 'kalimantan', [
            '1001' => ['Kota Balikpapan', ['100101' => ['Balikpapan Kota', '76111'], '100102' => ['Balikpapan Selatan', '76114']]],
            '1002' => ['Kota Samarinda', ['100201' => ['Samarinda Ulu', '75124']]],
        ]],
    ];

    /** [kode, nama kurir, layanan, deskripsi, tarif per kg (satu pulau), estimasi hari [min, max]] */
    private const SERVICES = [
        ['jne', 'JNE', 'REG', 'Layanan Reguler', 11000, [2, 3]],
        ['jne', 'JNE', 'YES', 'Yakin Esok Sampai', 19000, [1, 1]],
        ['sicepat', 'SiCepat', 'REG', 'Reguler', 10000, [2, 3]],
        ['sicepat', 'SiCepat', 'BEST', 'Besok Sampai Tujuan', 17000, [1, 1]],
        ['jnt', 'J&T Express', 'EZ', 'Reguler', 10500, [2, 4]],
        ['pos', 'POS Indonesia', 'Kilat Khusus', 'Pos Kilat Khusus', 9000, [3, 5]],
        ['tiki', 'TIKI', 'REG', 'Reguler', 10000, [3, 4]],
    ];

    public function __construct(private readonly ?string $originId = null) {}

    public function provinces(): array
    {
        return collect(self::REGIONS)
            ->map(fn (array $p, $id) => new Region((string) $id, $p[0]))
            ->sortBy('name')->values()->all();
    }

    public function cities(string $provinceId): array
    {
        return collect(self::REGIONS[$provinceId][2] ?? [])
            ->map(fn (array $c, $id) => new Region((string) $id, $c[0]))
            ->values()->all();
    }

    public function districts(string $cityId): array
    {
        $city = $this->findCity($cityId);

        return collect($city[1] ?? [])
            ->map(fn (array $d, $id) => new Region((string) $id, $d[0], $d[1]))
            ->values()->all();
    }

    public function rates(string $districtId, int $weight): array
    {
        $destination = $this->locate($districtId);

        if (! $destination) {
            return [];
        }

        $origin = $this->locate($this->originId ?: self::DEFAULT_ORIGIN) ?? $this->locate(self::DEFAULT_ORIGIN);

        [$multiplier, $extraDays] = match (true) {
            $origin['city'] === $destination['city'] => [0.7, 0],
            $origin['province'] === $destination['province'] => [0.85, 0],
            $origin['island'] === $destination['island'] => [1.0, 0],
            default => [1.8, 2],
        };

        $kg = max(1, (int) ceil($weight / 1000));

        return collect(self::SERVICES)
            ->filter(fn (array $s) => in_array($s[0], config('shop.shipping.couriers'), true))
            // Layanan "besok sampai" hanya untuk tujuan satu pulau.
            ->reject(fn (array $s) => $s[5][1] === 1 && $extraDays > 0)
            ->map(function (array $s) use ($multiplier, $extraDays, $kg) {
                [$min, $max] = [$s[5][0] + $extraDays, $s[5][1] + $extraDays];

                return new ShippingRate(
                    courier: $s[0],
                    courierName: $s[1],
                    service: $s[2],
                    description: $s[3],
                    cost: (int) (round($s[4] * $multiplier / 500) * 500) * $kg,
                    etd: ($min === $max ? $min : "{$min}-{$max}").' hari',
                );
            })
            ->values()
            ->all();
    }

    private function findCity(string $cityId): ?array
    {
        foreach (self::REGIONS as $province) {
            if (isset($province[2][$cityId])) {
                return $province[2][$cityId];
            }
        }

        return null;
    }

    /** @return array{province: string, city: string, island: string}|null */
    private function locate(string $districtId): ?array
    {
        foreach (self::REGIONS as $provinceId => $province) {
            foreach ($province[2] as $cityId => $city) {
                if (isset($city[1][$districtId])) {
                    return ['province' => (string) $provinceId, 'city' => (string) $cityId, 'island' => $province[1]];
                }
            }
        }

        return null;
    }
}
