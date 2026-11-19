<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ShippingUnavailableException;
use App\Http\Controllers\Controller;
use App\Services\Activity\ActivityLogger;
use App\Services\Settings\StoreSettings;
use App\Services\Shipping\CourierRecommender;
use App\Services\Shipping\ShippingService;
use App\Support\ShippingConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Admin → Pengaturan Toko: identitas, kontak, media sosial, pengiriman & pembayaran. */
class StoreSettingController extends Controller
{
    /** Nama kolom form => kunci config. */
    private const FORM = [
        'name' => 'shop.name',
        'full_name' => 'shop.full_name',
        'tagline' => 'shop.tagline',
        'email' => 'shop.email',
        'whatsapp' => 'shop.whatsapp',
        'whatsapp_button' => 'shop.whatsapp_button',
        'whatsapp_message' => 'shop.whatsapp_message',
        'address' => 'shop.address',
        'instagram' => 'shop.instagram',
        'tiktok' => 'shop.tiktok',
        'facebook' => 'shop.facebook',
        'tracking_sync' => 'shop.shipping.tracking_sync',
        'payment_expiry_hours' => 'shop.payment.expiry_hours',
        'auto_complete_days' => 'shop.orders.auto_complete_days',
        'wholesale_voucher' => 'shop.wholesale.voucher_combinable',
    ];

    /** Label kunci config untuk log aktivitas. */
    public const LABELS = [
        'shop.name' => 'Nama singkat',
        'shop.full_name' => 'Nama lengkap',
        'shop.tagline' => 'Tagline',
        'shop.email' => 'Email kontak',
        'shop.whatsapp' => 'WhatsApp',
        'shop.whatsapp_button' => 'Tombol WhatsApp melayang',
        'shop.whatsapp_message' => 'Pesan pembuka WhatsApp',
        'shop.address' => 'Alamat gudang',
        'shop.instagram' => 'Instagram',
        'shop.tiktok' => 'TikTok',
        'shop.facebook' => 'Facebook',
        'shop.shipping.origin_province_id' => 'Provinsi asal',
        'shop.shipping.origin_city_id' => 'Kota asal',
        'shop.shipping.origin_district_id' => 'Kecamatan asal (ID)',
        'shop.shipping.origin_label' => 'Wilayah asal',
        'shop.shipping.couriers' => 'Kurir aktif',
        'shop.shipping.tracking_sync' => 'Cek resi otomatis',
        'shop.shipping.biteship.origin_district_id' => 'Kecamatan asal Biteship (kode)',
        'shop.shipping.biteship.origin_label' => 'Wilayah asal (Biteship)',
        'shop.shipping.biteship.origin_postal_code' => 'Kode pos gudang (Biteship)',
        'shop.shipping.biteship.couriers' => 'Kurir aktif (Biteship)',
        'shop.shipping.biteship.instant_couriers' => 'Kurir instan (Biteship)',
        'shop.shipping.biteship.origin_latitude' => 'Koordinat gudang (lintang)',
        'shop.shipping.biteship.origin_longitude' => 'Koordinat gudang (bujur)',
        'shop.payment.expiry_hours' => 'Batas waktu bayar (jam)',
        'shop.wholesale.voucher_combinable' => 'Voucher untuk harga grosir',
        'shop.orders.auto_complete_days' => 'Selesai otomatis (hari)',
    ];

    public function __construct(private readonly StoreSettings $settings) {}

    public function edit(): View
    {
        return view('admin.store-settings', [
            // Asal & kurir mengikuti driver ongkir aktif (RajaOngkir dan Biteship disimpan terpisah).
            'values' => collect(self::FORM)->map(fn (string $key) => config($key))->all()
                + ['couriers' => ShippingConfig::couriers()]
                + collect(['province_id' => 'origin_province_id', 'city_id' => 'origin_city_id', 'district_id' => 'origin_district_id', 'origin_label' => 'origin_label', 'origin_postal_code' => 'origin_postal_code'])
                    ->map(fn (string $key) => config(ShippingConfig::key($key)))->all(),
            'defaults' => collect(self::FORM)->map(fn (string $key) => $this->settings->default($key))->all(),
            'couriers' => ShippingConfig::courierNames(),
            'trackableCouriers' => ShippingConfig::trackableCouriers(),
            'shippingDriver' => ShippingConfig::driver(),
            'shippingProvider' => ShippingConfig::providerName(),
            'shippingKeyMissing' => ! ShippingConfig::hasApiKey(),
            'instantCouriers' => StoreSettings::BITESHIP_INSTANT_COURIERS,
            'instant' => [
                'couriers' => (array) config('shop.shipping.biteship.instant_couriers'),
                'latitude' => config('shop.shipping.biteship.origin_latitude'),
                'longitude' => config('shop.shipping.biteship.origin_longitude'),
            ],
            'canRecommend' => CourierRecommender::available(),
        ]);
    }

    public function update(Request $request, ShippingService $shipping, ActivityLogger $logger): RedirectResponse
    {
        $request->merge([
            'whatsapp' => $this->normalizeWhatsapp($request->input('whatsapp')),
            'instagram' => $this->normalizeHandle($request->input('instagram')),
            'tiktok' => $this->normalizeHandle($request->input('tiktok')),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:30'],
            'full_name' => ['required', 'string', 'max:80'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:120'],
            'whatsapp' => ['nullable', 'regex:/^62\d{8,13}$/'],
            'whatsapp_button' => ['boolean'],
            'whatsapp_message' => ['nullable', 'string', 'max:300'],
            'address' => ['nullable', 'string', 'max:500'],
            'instagram' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9._]+$/'],
            'tiktok' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9._]+$/'],
            'facebook' => ['nullable', 'url:https', 'max:200'],
            'province_id' => ['nullable', 'string', 'max:20'],
            'city_id' => ['nullable', 'string', 'max:20'],
            'district_id' => ['nullable', 'string', 'max:20'],
            'origin_postal_code' => ['nullable', 'regex:/^\d{5}$/'],
            'instant_couriers' => ['nullable', 'array'],
            'instant_couriers.*' => [Rule::in(array_keys(StoreSettings::BITESHIP_INSTANT_COURIERS))],
            'origin_latitude' => ['nullable', 'required_with:origin_longitude', 'numeric', 'between:-90,90'],
            'origin_longitude' => ['nullable', 'required_with:origin_latitude', 'numeric', 'between:-180,180'],
            'couriers' => ['required', 'array', 'min:1'],
            'couriers.*' => [Rule::in(array_keys(ShippingConfig::courierNames()))],
            'tracking_sync' => ['boolean'],
            'payment_expiry_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'auto_complete_days' => ['required', 'integer', 'min:1', 'max:30'],
            'wholesale_voucher' => ['boolean'],
        ], [
            'whatsapp.regex' => 'Nomor WhatsApp tidak valid. Contoh: 081234567890.',
            'instagram.regex' => 'Isi username saja, tanpa spasi. Contoh: mga.official.',
            'tiktok.regex' => 'Isi username saja, tanpa spasi. Contoh: mga.official.',
            'couriers.required' => 'Pilih minimal satu kurir.',
            'origin_postal_code.regex' => 'Kode pos gudang harus 5 digit angka.',
        ], [
            'name' => 'nama singkat', 'full_name' => 'nama lengkap', 'district_id' => 'kecamatan asal',
            'payment_expiry_hours' => 'batas waktu bayar', 'auto_complete_days' => 'selesai otomatis',
            'whatsapp_message' => 'pesan pembuka WhatsApp',
        ]);

        $values = collect(self::FORM)
            ->mapWithKeys(fn (string $key, string $field) => [$key => $data[$field] ?? null])
            ->put(ShippingConfig::key('couriers'), $data['couriers'])
            ->put('shop.shipping.tracking_sync', $request->boolean('tracking_sync'))
            ->put('shop.whatsapp_button', $request->boolean('whatsapp_button'))
            ->put('shop.wholesale.voucher_combinable', $request->boolean('wholesale_voucher'))
            ->all();

        // Kecamatan asal (hanya bila dipilih lengkap): nama wilayah dicocokkan ke
        // data wilayah penyedia ongkir di server. Pilihan tidak lengkap = tidak diubah.
        if (filled($data['province_id'] ?? null) && filled($data['city_id'] ?? null) && filled($data['district_id'] ?? null)) {
            $values += $this->origin($shipping, $data['province_id'], $data['city_id'], $data['district_id']);
        }

        // Biteship menghitung ongkir dari kode pos; kosong = kode pos bawaan kecamatan asal.
        if (ShippingConfig::isBiteship() && $request->has('origin_postal_code')) {
            $values[ShippingConfig::key('origin_postal_code')] = $data['origin_postal_code'] ?? null;
        }

        // Kurir instan Biteship: butuh titik koordinat gudang.
        if (ShippingConfig::isBiteship() && $request->has('instant_submitted')) {
            $instant = array_values($data['instant_couriers'] ?? []);

            if ($instant !== [] && blank($data['origin_latitude'] ?? null)) {
                throw ValidationException::withMessages(['origin_latitude' => 'Taruh pin lokasi gudang di peta sebelum mengaktifkan kurir instan.']);
            }

            $values['shop.shipping.biteship.instant_couriers'] = $instant;
            $values['shop.shipping.biteship.origin_latitude'] = filled($data['origin_latitude'] ?? null) ? (string) round((float) $data['origin_latitude'], 7) : null;
            $values['shop.shipping.biteship.origin_longitude'] = filled($data['origin_longitude'] ?? null) ? (string) round((float) $data['origin_longitude'], 7) : null;
        }

        $changes = $this->settings->save($values);

        if ($changes) {
            $logger->log('settings.update', 'Mengubah pengaturan toko', properties: [
                'perubahan' => collect($changes)
                    ->except(['shop.shipping.origin_province_id', 'shop.shipping.origin_city_id', 'shop.shipping.biteship.origin_province_id', 'shop.shipping.biteship.origin_city_id'])
                    ->mapWithKeys(fn (array $pair, string $key) => [self::LABELS[$key] ?? $key => ['dari' => $this->display($pair[0]), 'menjadi' => $this->display($pair[1])]])
                    ->all(),
            ]);
        }

        return back()->with('success', $changes ? 'Pengaturan toko disimpan.' : 'Tidak ada perubahan.');
    }

    /** Rekomendasi kurir dari RajaOngkir untuk kecamatan asal (terpilih di form atau tersimpan). */
    public function couriers(Request $request, CourierRecommender $recommender): JsonResponse
    {
        $data = $request->validate([
            'province_id' => ['nullable', 'string', 'max:20', 'regex:/^\d+$/'],
            'district_id' => ['nullable', 'string', 'max:20', 'regex:/^\d+$/'],
        ]);
        $origin = $data['district_id'] ?? null;
        $province = $origin ? ($data['province_id'] ?? null) : null;
        $origin = $origin ?: (string) config('shop.shipping.origin_district_id');

        if ($origin === '' && CourierRecommender::available()) {
            return response()->json(['message' => 'Pilih kecamatan asal dulu.'], 422);
        }

        try {
            return response()->json($recommender->recommend($origin, $request->boolean('refresh'), $province));
        } catch (ShippingUnavailableException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }
    }

    /** @return array<string, string> */
    private function origin(ShippingService $shipping, string $provinceId, string $cityId, string $districtId): array
    {
        if (ShippingConfig::origin('district_id') === $districtId) {
            return [];
        }

        try {
            $region = $shipping->resolveRegion($provinceId, $cityId, $districtId);
        } catch (ShippingUnavailableException $e) {
            throw ValidationException::withMessages(['district_id' => $e->getMessage()]);
        }

        if (! $region) {
            throw ValidationException::withMessages(['district_id' => 'Kombinasi provinsi, kota, dan kecamatan tidak valid. Silakan pilih ulang.']);
        }

        return [
            ShippingConfig::key('origin_province_id') => $provinceId,
            ShippingConfig::key('origin_city_id') => $cityId,
            ShippingConfig::key('origin_district_id') => $districtId,
            ShippingConfig::key('origin_label') => "{$region['district']->name}, {$region['city']->name}, {$region['province']->name}",
        ];
    }

    private function normalizeWhatsapp(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return match (true) {
            $digits === '' => null,
            str_starts_with($digits, '0') => '62'.substr($digits, 1),
            str_starts_with($digits, '8') => '62'.$digits,
            default => $digits,
        };
    }

    /** "@mga.official" atau URL profil → "mga.official". */
    private function normalizeHandle(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (Str::contains($value, '/')) {
            $value = Str::of($value)->replaceMatches('#^https?://#', '')->explode('/')->filter()->values()->get(1, '');
        }

        return ltrim(trim($value), '@') ?: null;
    }

    private function display(mixed $value): mixed
    {
        return match (true) {
            is_bool($value) => $value ? 'Aktif' : 'Nonaktif',
            is_array($value) => implode(', ', array_map('strtoupper', $value)),
            default => $value,
        };
    }
}
