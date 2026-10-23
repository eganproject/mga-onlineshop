<?php

namespace App\Services\Shipping;

use App\Contracts\ShippingProvider;
use App\Data\Destination;
use App\Data\ShippingRate;

/**
 * Driver ongkir untuk development lokal. Tarif dihitung dari berat
 * agar alur checkout bisa dicoba tanpa API key RajaOngkir.
 */
class FakeShippingProvider implements ShippingProvider
{
    private const DESTINATIONS = [
        ['1001', 'Jakarta Selatan', 'DKI Jakarta', 'Kebayoran Baru', '12110'],
        ['1002', 'Jakarta Barat', 'DKI Jakarta', 'Grogol Petamburan', '11450'],
        ['2001', 'Kota Bandung', 'Jawa Barat', 'Coblong', '40132'],
        ['2002', 'Kota Bekasi', 'Jawa Barat', 'Bekasi Selatan', '17141'],
        ['3001', 'Kota Surabaya', 'Jawa Timur', 'Gubeng', '60281'],
        ['3002', 'Kota Malang', 'Jawa Timur', 'Lowokwaru', '65141'],
        ['4001', 'Kota Semarang', 'Jawa Tengah', 'Semarang Tengah', '50132'],
        ['5001', 'Kota Yogyakarta', 'DI Yogyakarta', 'Gondokusuman', '55221'],
        ['6001', 'Kota Medan', 'Sumatera Utara', 'Medan Baru', '20153'],
        ['7001', 'Kota Makassar', 'Sulawesi Selatan', 'Panakkukang', '90231'],
    ];

    private const SERVICES = [
        ['jne', 'JNE', 'REG', 'Layanan Reguler', 11000, '2-3'],
        ['jne', 'JNE', 'YES', 'Yakin Esok Sampai', 19000, '1'],
        ['sicepat', 'SiCepat', 'REG', 'Reguler', 10000, '2-3'],
        ['jnt', 'J&T Express', 'EZ', 'Reguler', 10500, '2-4'],
        ['pos', 'POS Indonesia', 'Kilat Khusus', 'Pos Kilat Khusus', 9000, '3-5'],
    ];

    public function searchDestinations(string $keyword): array
    {
        $keyword = mb_strtolower(trim($keyword));

        return collect(self::DESTINATIONS)
            ->filter(fn (array $d) => $keyword === ''
                || str_contains(mb_strtolower($d[1].' '.$d[2].' '.$d[3]), $keyword))
            ->map(fn (array $d) => new Destination(
                id: $d[0],
                label: "{$d[3]}, {$d[1]}, {$d[2]}",
                province: $d[2],
                city: $d[1],
                district: $d[3],
                postalCode: $d[4],
            ))
            ->values()
            ->all();
    }

    public function rates(string $destinationId, int $weight): array
    {
        $kg = max(1, (int) ceil($weight / 1000));

        return collect(self::SERVICES)
            ->filter(fn (array $s) => in_array($s[0], config('shop.shipping.couriers'), true))
            ->map(fn (array $s) => new ShippingRate(
                courier: $s[0],
                courierName: $s[1],
                service: $s[2],
                description: $s[3],
                cost: $s[4] * $kg,
                etd: $s[5].' hari',
            ))
            ->values()
            ->all();
    }
}
