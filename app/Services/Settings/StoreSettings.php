<?php

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Pengaturan toko yang bisa diubah dari panel admin.
 *
 * Setiap pengaturan memakai kunci config yang sama (mis. "shop.whatsapp"), jadi
 * kode lain tetap cukup membaca config('shop.whatsapp'). Saat aplikasi boot,
 * nilai dari tabel `settings` (di-cache) menimpa config; bila tidak ada isinya,
 * nilai dari .env/config/shop.php yang dipakai.
 */
class StoreSettings
{
    public const CACHE_KEY = 'shop:settings';

    /** Kunci config yang boleh diatur => tipe nilai. */
    public const FIELDS = [
        'shop.name' => 'string',
        'shop.full_name' => 'string',
        'shop.tagline' => 'string',
        'shop.email' => 'string',
        'shop.whatsapp' => 'string',
        'shop.address' => 'string',
        'shop.instagram' => 'string',
        'shop.tiktok' => 'string',
        'shop.facebook' => 'string',
        'shop.shipping.origin_province_id' => 'string',
        'shop.shipping.origin_city_id' => 'string',
        'shop.shipping.origin_district_id' => 'string',
        'shop.shipping.origin_label' => 'string',
        'shop.shipping.couriers' => 'array',
        'shop.shipping.tracking_sync' => 'bool',
        'shop.payment.expiry_hours' => 'int',
        'shop.orders.auto_complete_days' => 'int',
    ];

    /** Kode kurir RajaOngkir (Komerce) => nama tampilan. */
    public const COURIERS = [
        'jne' => 'JNE',
        'sicepat' => 'SiCepat',
        'jnt' => 'J&T Express',
        'pos' => 'POS Indonesia',
        'tiki' => 'TIKI',
        'anteraja' => 'AnterAja',
        'ninja' => 'Ninja Xpress',
        'lion' => 'Lion Parcel',
        'ide' => 'ID Express',
        'sap' => 'SAP Express',
        'wahana' => 'Wahana',
    ];

    /** Nilai config sebelum ditimpa (= isi .env / config/shop.php). */
    private array $defaults = [];

    private bool $captured = false;

    /** Timpa config dengan pengaturan tersimpan. Aman dipanggil sebelum tabel ada. */
    public function apply(): void
    {
        $this->captureDefaults();

        try {
            $stored = $this->stored();
        } catch (Throwable) {
            // Database belum siap (instalasi baru / sebelum migrate): pakai .env.
            return;
        }

        foreach (self::FIELDS as $key => $type) {
            config([$key => array_key_exists($key, $stored) ? $this->cast($stored[$key], $type) : ($this->defaults[$key] ?? null)]);
        }
    }

    /** @return array<string, mixed> nilai yang tersimpan di database saja */
    public function stored(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()
            ->whereIn('key', array_keys(self::FIELDS))
            ->pluck('value', 'key')
            ->all());
    }

    /** Nilai bawaan dari .env untuk satu kunci (ditampilkan sebagai petunjuk di form). */
    public function default(string $key): mixed
    {
        $this->captureDefaults();

        return $this->defaults[$key] ?? null;
    }

    /** Nilai yang sedang berlaku. */
    public function get(string $key): mixed
    {
        return config($key);
    }

    /**
     * Simpan pengaturan. Nilai kosong (null/'') atau sama dengan .env tidak
     * disimpan, sehingga mengikuti .env bila nanti .env diubah.
     *
     * @param  array<string, mixed>  $values  kunci config => nilai baru
     * @return array<string, array{0: mixed, 1: mixed}> kunci yang berubah => [lama, baru]
     */
    public function save(array $values): array
    {
        $this->captureDefaults();
        $values = Arr::only($values, array_keys(self::FIELDS));
        $changes = [];

        foreach ($values as $key => $value) {
            $type = self::FIELDS[$key];
            $value = $value === '' || $value === null ? null : $this->cast($value, $type);
            $before = config($key);
            $default = $this->defaults[$key] ?? null;
            $after = $value ?? $default;

            if ($value === null || $value == $default) {
                Setting::whereKey($key)->delete();
            } else {
                Setting::updateOrCreate(['key' => $key], ['value' => $value]);
            }

            if ($before != $after) {
                $changes[$key] = [$before, $after];
            }
        }

        $this->flush();
        $this->apply();

        return $changes;
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function captureDefaults(): void
    {
        if ($this->captured) {
            return;
        }

        foreach (array_keys(self::FIELDS) as $key) {
            $this->defaults[$key] = config($key);
        }
        $this->captured = true;
    }

    private function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'array' => array_values(array_filter((array) $value, 'filled')),
            default => is_scalar($value) ? trim((string) $value) : null,
        };
    }
}
