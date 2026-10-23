<?php

namespace App\Contracts;

use App\Data\Region;
use App\Data\ShippingRate;

/**
 * Penyedia data wilayah dan ongkos kirim. Wilayah bertingkat:
 * provinsi → kota/kabupaten → kecamatan. Ongkir dihitung per kecamatan.
 */
interface ShippingProvider
{
    /** @return list<Region> */
    public function provinces(): array;

    /** @return list<Region> */
    public function cities(string $provinceId): array;

    /** @return list<Region> */
    public function districts(string $cityId): array;

    /**
     * Hitung ongkir dari gudang ke kecamatan tujuan untuk berat tertentu (gram).
     *
     * @return list<ShippingRate>
     */
    public function rates(string $districtId, int $weight): array;
}
