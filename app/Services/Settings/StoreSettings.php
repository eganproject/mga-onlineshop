<?php

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pengaturan toko yang bisa diubah dari panel admin.
 *
 * Setiap pengaturan memakai kunci config yang sama (mis. "shop.whatsapp"), jadi
 * kode lain tetap cukup membaca config('shop.whatsapp'). Saat aplikasi boot,
 * nilai dari tabel `settings` (di-cache) menimpa config; bila tidak ada isinya,
 * nilai dari .env/config/shop.php yang dipakai.
 *
 * Kunci bertipe "secret" (server key Midtrans, API key RajaOngkir) disimpan
 * terenkripsi dengan APP_KEY, baik di database maupun di cache.
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
        'shop.whatsapp_button' => 'bool',
        'shop.whatsapp_message' => 'string',
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
        'shop.storefront.hero_image' => 'string',
        'shop.storefront.hero_image_mobile' => 'string',
        'shop.storefront.hero_eyebrow' => 'string',
        'shop.storefront.hero_title' => 'string',
        'shop.storefront.hero_subtitle' => 'string',
        'shop.storefront.hero_primary_label' => 'string',
        'shop.storefront.hero_primary_url' => 'string',
        'shop.storefront.hero_secondary_label' => 'string',
        'shop.storefront.hero_secondary_url' => 'string',
        'shop.storefront.hero_text' => 'string',
        'shop.storefront.announcements' => 'array',
        'shop.storefront.best_seller_mode' => 'string',
        'shop.storefront.best_seller_products' => 'array',
        'shop.storefront.best_seller_fill' => 'bool',

        // Pajak, biaya & invoice (Admin › Pajak & Biaya).
        'shop.billing.tax_enabled' => 'bool',
        'shop.billing.tax_label' => 'string',
        'shop.billing.tax_rate' => 'float',
        'shop.billing.tax_dpp' => 'string',
        'shop.billing.tax_mode' => 'string',
        'shop.billing.tax_shipping' => 'bool',
        'shop.billing.tax_price_note' => 'bool',
        'shop.billing.service_fee' => 'int',
        'shop.billing.service_fee_label' => 'string',
        'shop.billing.payment_fee_label' => 'string',
        'shop.billing.payment_fee_gross_up' => 'bool',
        'shop.billing.payment_fee_vat' => 'float',
        'shop.billing.payment_methods' => 'map',
        'shop.billing.rounding_mode' => 'string',
        'shop.billing.rounding_unit' => 'int',
        'shop.billing.invoice_prefix' => 'string',
        'shop.billing.invoice_format' => 'string',
        'shop.billing.invoice_padding' => 'int',
        'shop.billing.invoice_reset' => 'string',
        'shop.billing.invoice_issue_on' => 'string',
        'shop.billing.company_name' => 'string',
        'shop.billing.company_npwp' => 'string',
        'shop.billing.company_address' => 'string',
        'shop.billing.company_phone' => 'string',
        'shop.billing.company_email' => 'string',
        'shop.billing.is_pkp' => 'bool',
        'shop.billing.buyer_tax_info' => 'bool',
        'shop.billing.invoice_notes' => 'string',
        'shop.billing.invoice_footer' => 'string',
        'shop.billing.signatory_name' => 'string',
        'shop.billing.signatory_title' => 'string',

        // Integrasi (Admin › Integrasi, khusus superadmin).
        'shop.payment.driver' => 'string',
        'services.midtrans.is_production' => 'bool',
        'services.midtrans.merchant_id' => 'string',
        'services.midtrans.client_key' => 'string',
        'services.midtrans.server_key' => 'secret',
        'shop.shipping.driver' => 'string',
        'services.rajaongkir.api_key' => 'secret',
        'services.firebase.login_enabled' => 'bool',
        'services.firebase.api_key' => 'string',
        'services.firebase.auth_domain' => 'string',
        'services.firebase.project_id' => 'string',
        'services.firebase.app_id' => 'string',
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
            $value = array_key_exists($key, $stored) ? $this->fromStorage($stored[$key], $type) : null;
            config([$key => $value ?? ($this->defaults[$key] ?? null)]);
        }

        $this->applyDerived($stored);
    }

    /** Apakah nilai yang berlaku untuk kunci ini berasal dari database (bukan .env). */
    public function isStored(string $key): bool
    {
        try {
            return array_key_exists($key, $this->stored());
        } catch (Throwable) {
            return false;
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
                Setting::updateOrCreate(['key' => $key], ['value' => $type === 'secret' ? Crypt::encryptString($value) : $value]);
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

    /** Nilai turunan yang di config dihitung dari kunci lain. */
    private function applyDerived(array $stored): void
    {
        // Project ID diganti dari admin tanpa auth domain: pakai domain bawaan Firebase.
        if (! array_key_exists('services.firebase.auth_domain', $stored)
            && array_key_exists('services.firebase.project_id', $stored)) {
            config(['services.firebase.auth_domain' => config('services.firebase.project_id').'.firebaseapp.com']);
        }

        config(['services.firebase.enabled' => (bool) config('services.firebase.login_enabled')
            && filled(config('services.firebase.api_key'))
            && filled(config('services.firebase.project_id'))]);
    }

    private function fromStorage(mixed $value, string $type): mixed
    {
        if ($type !== 'secret') {
            return $this->cast($value, $type);
        }

        try {
            return Crypt::decryptString((string) $value);
        } catch (DecryptException) {
            // APP_KEY berganti: kunci lama tidak terbaca, kembali ke .env.
            Log::warning('Pengaturan rahasia tidak bisa didekripsi (APP_KEY berubah?). Isi ulang di Admin › Integrasi.');

            return null;
        }
    }

    private function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'float' => round((float) str_replace(',', '.', (string) $value), 4),
            'map' => (array) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'array' => array_values(array_filter((array) $value, 'filled')),
            default => is_scalar($value) ? trim((string) $value) : null,
        };
    }
}
