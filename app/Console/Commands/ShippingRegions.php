<?php

namespace App\Console\Commands;

use App\Data\Region;
use App\Exceptions\ShippingUnavailableException;
use App\Services\Shipping\ShippingService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Bantu mencari ID wilayah, terutama ID kecamatan gudang untuk
 * SHOP_ORIGIN_DISTRICT_ID. Contoh:
 *   php artisan ongkir:wilayah                  (daftar provinsi)
 *   php artisan ongkir:wilayah --provinsi=6     (kota di provinsi 6)
 *   php artisan ongkir:wilayah --kota=151       (kecamatan di kota 151)
 */
#[Signature('ongkir:wilayah {--provinsi= : ID provinsi untuk melihat daftar kota} {--kota= : ID kota untuk melihat daftar kecamatan} {--cari= : Saring berdasarkan nama}')]
#[Description('Tampilkan ID provinsi/kota/kecamatan dari penyedia ongkir aktif')]
class ShippingRegions extends Command
{
    public function handle(ShippingService $shipping): int
    {
        try {
            [$level, $regions] = match (true) {
                filled($this->option('kota')) => ['Kecamatan', $shipping->districts($this->option('kota'))],
                filled($this->option('provinsi')) => ['Kota/Kabupaten', $shipping->cities($this->option('provinsi'))],
                default => ['Provinsi', $shipping->provinces()],
            };
        } catch (ShippingUnavailableException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($keyword = mb_strtolower((string) $this->option('cari'))) {
            $regions = array_values(array_filter($regions, fn (Region $r) => str_contains(mb_strtolower($r->name), $keyword)));
        }

        if ($regions === []) {
            $this->warn("{$level} tidak ditemukan.");

            return self::SUCCESS;
        }

        $this->table(['ID', $level, 'Kode pos'], array_map(fn (Region $r) => [$r->id, $r->name, $r->postalCode ?? '-'], $regions));

        if ($level === 'Kecamatan') {
            $this->line('Salin ID kecamatan gudang ke SHOP_ORIGIN_DISTRICT_ID di file .env.');
        }

        return self::SUCCESS;
    }
}
