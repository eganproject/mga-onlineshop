<?php

namespace App\Support;

use App\Services\Settings\StoreSettings;

/**
 * Pengaturan ongkir untuk driver yang sedang aktif. RajaOngkir (dan simulasi)
 * memakai kunci `shop.shipping.*`; Biteship memakai `shop.shipping.biteship.*`
 * karena ID wilayah dan kode kurirnya berbeda. Kode lain cukup memanggil kelas
 * ini, tidak perlu tahu driver mana yang dipilih.
 */
class ShippingConfig
{
    public const BITESHIP = 'biteship';

    public const RAJAONGKIR = 'rajaongkir';

    public static function driver(): string
    {
        return (string) config('shop.shipping.driver', 'fake');
    }

    public static function isBiteship(): bool
    {
        return self::driver() === self::BITESHIP;
    }

    /** Nama penyedia untuk ditampilkan di panel admin. */
    public static function providerName(): string
    {
        return match (self::driver()) {
            self::BITESHIP => 'Biteship',
            self::RAJAONGKIR => 'RajaOngkir',
            default => 'Simulasi',
        };
    }

    /** Kunci config untuk driver aktif, mis. key('couriers'), key('origin_district_id'). */
    public static function key(string $name): string
    {
        return (self::isBiteship() ? 'shop.shipping.biteship.' : 'shop.shipping.').$name;
    }

    /** Bagian asal pengiriman: province_id | city_id | district_id | label | postal_code. */
    public static function origin(string $part = 'district_id'): ?string
    {
        if ($part === 'postal_code' && ! self::isBiteship()) {
            return null;
        }

        $value = config(self::key('origin_'.$part));

        return filled($value) ? (string) $value : null;
    }

    /** Kode kurir yang didukung driver aktif => nama tampilan. */
    public static function courierNames(): array
    {
        return self::isBiteship() ? StoreSettings::BITESHIP_COURIERS : StoreSettings::COURIERS;
    }

    /** Kurir yang resinya bisa dilacak lewat driver aktif. */
    public static function trackableCouriers(): array
    {
        return self::isBiteship() ? array_keys(StoreSettings::BITESHIP_COURIERS) : StoreSettings::TRACKABLE_COURIERS;
    }

    /** Kurir aktif yang dikenal driver saat ini (kode lama/tak dikenal dibuang). */
    public static function couriers(): array
    {
        $known = array_keys(self::courierNames());
        $couriers = array_values(array_intersect((array) config(self::key('couriers')), $known));

        return $couriers ?: ['jne'];
    }

    /** Kunci API driver aktif terisi (simulasi tidak butuh kunci). */
    public static function hasApiKey(): bool
    {
        return match (self::driver()) {
            self::BITESHIP => filled(config('services.biteship.api_key')),
            self::RAJAONGKIR => filled(config('services.rajaongkir.api_key')),
            default => true,
        };
    }

    /**
     * Sumber ID wilayah sebuah alamat: alamat yang dipilih saat Biteship aktif
     * memakai kode Kemendagri, selainnya ID RajaOngkir/simulasi.
     */
    public static function regionSource(): string
    {
        return self::isBiteship() ? self::BITESHIP : self::RAJAONGKIR;
    }
}
