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

    /**
     * Titik koordinat gudang [lat, lng] untuk kurir instan; null bila belum diatur
     * atau driver bukan Biteship.
     *
     * @return array{0: float, 1: float}|null
     */
    public static function originCoordinates(): ?array
    {
        if (! self::isBiteship()) {
            return null;
        }

        return self::coordinates(config('shop.shipping.biteship.origin_latitude'), config('shop.shipping.biteship.origin_longitude'));
    }

    /** Kurir instan yang dicentang admin (hanya Biteship). */
    public static function instantCouriers(): array
    {
        if (! self::isBiteship()) {
            return [];
        }

        return array_values(array_intersect((array) config('shop.shipping.biteship.instant_couriers'), array_keys(StoreSettings::BITESHIP_INSTANT_COURIERS)));
    }

    /** Kurir instan siap ditawarkan: ada yang dicentang dan koordinat gudang terisi. */
    public static function instantEnabled(): bool
    {
        return self::instantCouriers() !== [] && self::originCoordinates() !== null;
    }

    /**
     * Pasangan lintang/bujur yang valid, atau null.
     *
     * @return array{0: float, 1: float}|null
     */
    public static function coordinates(mixed $latitude, mixed $longitude): ?array
    {
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        [$lat, $lng] = [(float) $latitude, (float) $longitude];

        return abs($lat) <= 90 && abs($lng) <= 180 && ($lat != 0.0 || $lng != 0.0) ? [$lat, $lng] : null;
    }

    /** Jarak garis lurus dua titik [lat, lng] dalam km (haversine). */
    public static function distanceKm(array $from, array $to): float
    {
        [$lat1, $lng1, $lat2, $lng2] = array_map('deg2rad', [$from[0], $from[1], $to[0], $to[1]]);
        $a = sin(($lat2 - $lat1) / 2) ** 2 + cos($lat1) * cos($lat2) * sin(($lng2 - $lng1) / 2) ** 2;

        return 6371 * 2 * asin(min(1, sqrt($a)));
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
