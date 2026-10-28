<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\Activity\ActivityLogger;
use App\Services\Settings\StoreSettings;
use App\Support\Seo;
use App\Support\Storefront;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Admin → SEO: judul & deskripsi beranda, gambar bagikan, verifikasi Search Console/Bing, Google Analytics. */
class SeoSettingController extends Controller
{
    /** Nama kolom form => kunci config (teks). */
    private const TEXT = [
        'home_title' => 'shop.seo.home_title',
        'description' => 'shop.seo.description',
        'google_verification' => 'shop.seo.google_verification',
        'bing_verification' => 'shop.seo.bing_verification',
        'analytics_id' => 'shop.seo.analytics_id',
    ];

    private const IMAGE = 'shop.seo.default_image';

    public const LABELS = [
        'shop.seo.home_title' => 'Judul beranda (SEO)',
        'shop.seo.description' => 'Deskripsi toko (SEO)',
        'shop.seo.default_image' => 'Gambar bagikan bawaan',
        'shop.seo.google_verification' => 'Verifikasi Google Search Console',
        'shop.seo.bing_verification' => 'Verifikasi Bing',
        'shop.seo.analytics_id' => 'Google Analytics',
    ];

    public function __construct(private readonly StoreSettings $settings) {}

    public function edit(): View
    {
        $products = Product::active()->without('coverImage');

        return view('admin.seo', [
            // Hanya nilai yang diisi di admin; nilai .env/bawaan tampil sebagai petunjuk.
            'values' => collect(self::TEXT)->map(fn (string $key) => $this->settings->isStored($key) ? config($key) : null)->all(),
            'defaults' => collect(self::TEXT)->map(fn (string $key) => $this->settings->default($key))->all(),
            'homeTitle' => Seo::homeTitle(),
            'image' => Storefront::url(config(self::IMAGE)),
            'customImage' => $this->settings->isStored(self::IMAGE),
            'indexing' => (bool) config('shop.seo.indexing'),
            'health' => [
                'products' => (clone $products)->count(),
                'no_description' => (clone $products)->where(fn ($q) => $q->whereNull('description')->orWhere('description', ''))->count(),
                'no_image' => (clone $products)->doesntHave('images')->count(),
                'categories_no_description' => Category::active()->where(fn ($q) => $q->whereNull('description')->orWhere('description', ''))->count(),
            ],
        ]);
    }

    public function update(Request $request, ActivityLogger $logger): RedirectResponse
    {
        // Admin sering menempel tag meta utuh dari Search Console: ambil isi content-nya saja.
        $request->merge([
            'google_verification' => $this->verificationCode($request->input('google_verification')),
            'bing_verification' => $this->verificationCode($request->input('bing_verification')),
            'analytics_id' => filled($request->input('analytics_id')) ? strtoupper(trim($request->input('analytics_id'))) : null,
        ]);

        $data = $request->validate([
            'home_title' => ['nullable', 'string', 'max:70'],
            'description' => ['nullable', 'string', 'max:160'],
            'google_verification' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'bing_verification' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'analytics_id' => ['nullable', 'string', 'regex:'.Seo::ANALYTICS_PATTERN],
            'default_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=600'],
            'remove_default_image' => ['boolean'],
        ], [
            'google_verification.regex' => 'Kode tidak valid. Tempel tag meta dari Search Console atau isi bagian content-nya saja.',
            'bing_verification.regex' => 'Kode tidak valid. Tempel tag meta dari Bing atau isi bagian content-nya saja.',
            'analytics_id.regex' => 'Format ID Google Analytics 4 adalah G-XXXXXXXXXX.',
            'default_image.dimensions' => 'Gambar minimal lebar 600 px (ideal 1200×630 px).',
        ], [
            'home_title' => 'judul beranda', 'description' => 'deskripsi toko', 'default_image' => 'gambar bagikan',
            'analytics_id' => 'ID Google Analytics',
        ]);

        $values = collect(self::TEXT)->mapWithKeys(fn (string $key, string $field) => [$key => $data[$field] ?? null])->all();

        $current = config(self::IMAGE);
        $uploaded = $current && Str::startsWith($current, Storefront::UPLOAD_DIR.'/');
        $obsolete = null;

        if ($request->hasFile('default_image')) {
            $values[self::IMAGE] = $request->file('default_image')->store(Storefront::UPLOAD_DIR, 'public');
            $obsolete = $uploaded ? $current : null;
        } elseif ($request->boolean('remove_default_image')) {
            $values[self::IMAGE] = null;
            $obsolete = $uploaded ? $current : null;
        }

        $changes = $this->settings->save($values);

        if ($obsolete) {
            Storage::disk('public')->delete($obsolete);
        }

        if ($changes) {
            $logger->log('settings.update', 'Mengubah pengaturan SEO', properties: [
                'perubahan' => collect($changes)
                    ->mapWithKeys(fn (array $pair, string $key) => [self::LABELS[$key] ?? $key => ['dari' => $pair[0], 'menjadi' => $pair[1]]])
                    ->all(),
            ]);
        }

        return back()->with('success', $changes ? 'Pengaturan SEO disimpan.' : 'Tidak ada perubahan.');
    }

    /** `<meta name="google-site-verification" content="abc">` → "abc". */
    private function verificationCode(?string $value): ?string
    {
        $value = trim((string) $value);

        if (preg_match('/content\s*=\s*["\']([^"\']+)["\']/i', $value, $m)) {
            $value = trim($m[1]);
        }

        return $value === '' ? null : $value;
    }
}
