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
     * $postalCode = kode pos di alamat tujuan; dipakai driver berbasis kode pos
     * (Biteship) bila ID kecamatannya berasal dari penyedia lain.
     * $coordinates = pin lokasi tujuan [lat, lng] bila ada (kurir instan Biteship).
     *
     * @param  array{0: float, 1: float}|null  $coordinates
     * @return list<ShippingRate>
     */
    public function rates(string $districtId, int $weight, ?string $postalCode = null, ?array $coordinates = null): array;
}
