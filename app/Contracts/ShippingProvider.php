<?php

namespace App\Contracts;

use App\Data\Destination;
use App\Data\ShippingRate;

interface ShippingProvider
{
    /**
     * Cari lokasi tujuan berdasarkan kata kunci (nama kecamatan/kota).
     *
     * @return list<Destination>
     */
    public function searchDestinations(string $keyword): array;

    /**
     * Hitung ongkir dari gudang ke tujuan untuk berat tertentu (gram).
     *
     * @return list<ShippingRate>
     */
    public function rates(string $destinationId, int $weight): array;
}
