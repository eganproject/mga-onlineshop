<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Settings\StoreSettings;
use App\Support\Storefront;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Admin → Tampilan Beranda: foto & teks hero, serta pengumuman di topbar. */
class StorefrontController extends Controller
{
    /** Nama kolom form => kunci config (teks). */
    private const TEXT = [
        'hero_eyebrow' => 'shop.storefront.hero_eyebrow',
        'hero_title' => 'shop.storefront.hero_title',
        'hero_subtitle' => 'shop.storefront.hero_subtitle',
        'hero_primary_label' => 'shop.storefront.hero_primary_label',
        'hero_primary_url' => 'shop.storefront.hero_primary_url',
        'hero_secondary_label' => 'shop.storefront.hero_secondary_label',
        'hero_secondary_url' => 'shop.storefront.hero_secondary_url',
        'hero_text' => 'shop.storefront.hero_text',
    ];

    /** Kolom unggahan => kunci config. */
    private const IMAGES = [
        'hero_image' => 'shop.storefront.hero_image',
        'hero_image_mobile' => 'shop.storefront.hero_image_mobile',
    ];

    public function __construct(private readonly StoreSettings $settings) {}

    public function edit(): View
    {
        $keys = self::TEXT + self::IMAGES;

        return view('admin.storefront', [
            'values' => collect($keys)->map(fn (string $key) => config($key))->all()
                + ['announcements' => implode("\n", Storefront::announcements())],
            'defaults' => collect(self::TEXT)->map(fn (string $key) => $this->settings->default($key))->all(),
            'images' => collect(self::IMAGES)->map(fn (string $key) => Storefront::url(config($key)))->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $link = ['nullable', 'string', 'max:255', 'regex:#^(/(?!/)|https?://)#'];

        $data = $request->validate([
            'hero_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=1200'],
            'hero_image_mobile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:min_width=600'],
            'remove_hero_image' => ['boolean'],
            'remove_hero_image_mobile' => ['boolean'],
            'hero_eyebrow' => ['nullable', 'string', 'max:60'],
            'hero_title' => ['nullable', 'string', 'max:90'],
            'hero_subtitle' => ['nullable', 'string', 'max:200'],
            'hero_primary_label' => ['nullable', 'string', 'max:30'],
            'hero_primary_url' => $link,
            'hero_secondary_label' => ['nullable', 'string', 'max:30'],
            'hero_secondary_url' => $link,
            'hero_text' => ['required', 'in:light,dark'],
            'announcements' => ['nullable', 'string', 'max:1000'],
        ], [
            'hero_primary_url.regex' => 'Awali dengan "/" untuk halaman toko (mis. /produk) atau https:// untuk link luar.',
            'hero_secondary_url.regex' => 'Awali dengan "/" untuk halaman toko (mis. /produk) atau https:// untuk link luar.',
            'hero_image.dimensions' => 'Foto hero minimal lebar 1200 px supaya tetap tajam di layar besar.',
            'hero_image_mobile.dimensions' => 'Foto versi ponsel minimal lebar 600 px.',
        ], [
            'hero_image' => 'foto hero', 'hero_image_mobile' => 'foto versi ponsel', 'hero_title' => 'judul',
            'hero_primary_url' => 'link tombol utama', 'hero_secondary_url' => 'link tombol kedua', 'announcements' => 'pengumuman',
        ]);

        $values = collect(self::TEXT)->mapWithKeys(fn (string $key, string $field) => [$key => $data[$field] ?? null])->all();

        $values['shop.storefront.announcements'] = collect(preg_split('/\R/', (string) ($data['announcements'] ?? '')))
            ->map(fn (string $line) => Str::limit(trim($line), 120, ''))
            ->filter()->take(6)->values()->all() ?: null;

        $obsolete = [];
        foreach (self::IMAGES as $field => $key) {
            $current = config($key);
            $uploaded = $current && Str::startsWith($current, Storefront::UPLOAD_DIR.'/');

            if ($request->hasFile($field)) {
                $values[$key] = $request->file($field)->store(Storefront::UPLOAD_DIR, 'public');
                $uploaded && $obsolete[] = $current;
            } elseif ($request->boolean('remove_'.$field)) {
                $values[$key] = null;
                $uploaded && $obsolete[] = $current;
            }
        }

        $changes = $this->settings->save($values);

        // Hapus file lama setelah pengaturan baru tersimpan.
        Storage::disk('public')->delete($obsolete);

        return back()->with('success', $changes ? 'Tampilan beranda disimpan.' : 'Tidak ada perubahan.');
    }
}
