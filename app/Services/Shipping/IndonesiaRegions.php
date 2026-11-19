<?php

namespace App\Services\Shipping;

use App\Data\Region;
use RuntimeException;

/**
 * Data wilayah Indonesia bawaan (38 provinsi, 514 kota/kabupaten, 7.285
 * kecamatan + kode pos) dari database/data/indonesia-regions.json, bersumber
 * dari Kepmendagri 300.2.2-2138/2025 (github.com/cahyadsn/wilayah, lisensi MIT).
 *
 * Dipakai driver Biteship, yang tidak punya API daftar provinsi/kota/kecamatan:
 * pelanggan tetap memilih wilayah bertingkat, lalu ongkir dihitung dari kode
 * pos kecamatannya. ID = kode Kemendagri (provinsi 2 digit, kota 4, kecamatan 6).
 */
class IndonesiaRegions
{
    /** @var array{provinces: list<array>, cities: array<string, list<array>>, districts: array<string, list<array>>}|null */
    private static ?array $data = null;

    /** @return list<Region> */
    public function provinces(): array
    {
        return $this->toRegions($this->data()['provinces']);
    }

    /** @return list<Region> */
    public function cities(string $provinceId): array
    {
        return $this->toRegions($this->data()['cities'][$provinceId] ?? []);
    }

    /** @return list<Region> */
    public function districts(string $cityId): array
    {
        return $this->toRegions($this->data()['districts'][$cityId] ?? []);
    }

    /** Kode pos bawaan sebuah kecamatan; null bila ID bukan kode Kemendagri. */
    public function postalCode(string $districtId): ?string
    {
        if (! preg_match('/^\d{6}$/', $districtId)) {
            return null;
        }

        foreach ($this->data()['districts'][substr($districtId, 0, 4)] ?? [] as $row) {
            if ($row[0] === $districtId) {
                return $row[2] ?? null;
            }
        }

        return null;
    }

    /** @return list<Region> */
    private function toRegions(array $rows): array
    {
        $regions = array_map(fn (array $row) => new Region((string) $row[0], (string) $row[1], $row[2] ?? null), $rows);
        usort($regions, fn (Region $a, Region $b) => strcmp($a->name, $b->name));

        return $regions;
    }

    private function data(): array
    {
        if (self::$data === null) {
            $path = database_path('data/indonesia-regions.json');
            $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

            if (! is_array($data) || ! isset($data['provinces'], $data['cities'], $data['districts'])) {
                throw new RuntimeException('Data wilayah (database/data/indonesia-regions.json) tidak ditemukan atau rusak.');
            }

            self::$data = $data;
        }

        return self::$data;
    }
}
