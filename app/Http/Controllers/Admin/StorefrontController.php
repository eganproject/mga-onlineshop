<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Settings\StoreSettings;
use App\Services\Storefront\BestSellers;
use App\Support\Storefront;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Admin → Tampilan Beranda: foto & teks hero, pengumuman di topbar, dan isi tab Best Seller. */
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
            'bestSeller' => [
                'mode' => BestSellers::isManual() ? 'manual' : 'auto',
                'fill' => (bool) config('shop.storefront.best_seller_fill', true),
                'products' => $this->pickedProducts(BestSellers::pickedIds()),
            ],
        ]);
    }

    /** Simpan mode & urutan produk Best Seller (form terpisah dari hero). */
    public function updateBestSellers(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'best_seller_mode' => ['required', 'in:auto,manual'],
            'best_seller_fill' => ['boolean'],
            'best_seller_products' => ['nullable', 'required_if:best_seller_mode,manual', 'array', 'max:'.BestSellers::MAX_PICKS],
            'best_seller_products.*' => ['integer', 'distinct', Rule::exists('products', 'id')],
        ], [
            'best_seller_products.max' => 'Pilih maksimal '.BestSellers::MAX_PICKS.' produk.',
            'best_seller_products.required_if' => 'Pilih minimal 1 produk untuk mode manual.',
        ], [
            'best_seller_products' => 'produk best seller',
        ]);

        $ids = array_map('intval', $data['best_seller_products'] ?? []);

        $changes = $this->settings->save([
            'shop.storefront.best_seller_mode' => $data['best_seller_mode'],
            'shop.storefront.best_seller_fill' => $request->boolean('best_seller_fill'),
            'shop.storefront.best_seller_products' => $ids,
        ]);

        return redirect()->to(route('admin.storefront.edit').'#best-seller')
            ->with('success', $changes ? 'Best Seller disimpan.' : 'Tidak ada perubahan.');
    }

    /** Pencarian produk untuk pemilih Best Seller (JSON). */
    public function searchProducts(Request $request): JsonResponse
    {
        $term = Str::limit(trim((string) $request->query('q')), 100, '');

        $products = Product::query()
            ->search($term)
            ->orderByDesc('is_active')->orderBy('name')
            ->take(20)->get();

        return response()->json($products->map(fn (Product $p) => $this->productPayload($p))->all());
    }

    /** @param  list<int>  $ids */
    private function pickedProducts(array $ids): array
    {
        $products = Product::whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->map(fn (int $id) => $products->get($id))->filter()
            ->map(fn (Product $p) => $this->productPayload($p))->values()->all();
    }

    private function productPayload(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'image' => $product->imageUrl(),
            'price' => $product->formattedPrice(),
            'stock' => $product->stock,
            'active' => $product->is_active,
        ];
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
