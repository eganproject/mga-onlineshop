<?php

namespace App\Services\Catalog;

use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\Transfer\ImageDownloader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Katalog produk asli MGA (dari toko Shopee gudang.acc) yang disimpan di
 * database/data/mga-catalog.json. Dipakai seeder dan `php artisan shop:import-mga`.
 *
 * Produk yang sudah ada (dicocokkan lewat slug) tidak ditimpa supaya harga/stok
 * yang sudah diubah admin aman; yang ditambahkan hanya foto bila galerinya kosong.
 * Produk tanpa harga di sumber masuk sebagai nonaktif sampai harganya diisi.
 */
class MgaCatalog
{
    private ?array $data = null;

    public function __construct(
        private readonly StockService $stock,
        private readonly ImageDownloader $images,
    ) {}

    public static function path(): string
    {
        return database_path('data/mga-catalog.json');
    }

    public function data(): array
    {
        if ($this->data === null) {
            $data = json_decode((string) @file_get_contents(self::path()), true);

            if (! is_array($data) || empty($data['products'])) {
                throw new RuntimeException('File katalog MGA tidak ditemukan atau rusak: '.self::path());
            }

            $this->data = $data;
        }

        return $this->data;
    }

    /** @return list<string> */
    public function slugs(): array
    {
        return array_column($this->data()['products'], 'slug');
    }

    /**
     * @param  callable(string): void|null  $progress  dipanggil per produk (untuk progress bar)
     * @return array{categories: int, created: int, skipped: int, images: int, warnings: list<string>}
     */
    public function import(bool $withImages = false, int $initialStock = 25, ?callable $progress = null): array
    {
        $result = ['categories' => 0, 'created' => 0, 'skipped' => 0, 'images' => 0, 'warnings' => []];
        $categories = $this->importCategories($result);

        foreach ($this->data()['products'] as $item) {
            $product = Product::where('slug', $item['slug'])->first();

            if ($product) {
                $result['skipped']++;
            } else {
                $product = $this->createProduct($item, $categories[$item['category']] ?? null, $initialStock);
                $result['created']++;
            }

            if ($withImages && $item['images'] && ! $product->images()->exists()) {
                $base = $this->data()['image_base'] ?? '';
                $urls = array_map(fn (string $path) => Str::startsWith($path, 'http') ? $path : $base.$path, $item['images']);
                $warnings = $this->images->attach($product, $urls);
                $result['images'] += count($urls) - count($warnings);
                array_push($result['warnings'], ...array_map(fn ($w) => "{$product->name}: {$w}", $warnings));
            }

            if ($progress) {
                $progress($product->name);
            }
        }

        return $result;
    }

    /**
     * Hapus produk & kategori yang bukan bagian katalog MGA (mis. data demo lama).
     * Produk yang pernah dipesan hanya dinonaktifkan supaya riwayat pesanan utuh.
     *
     * Semua kategori di luar katalog MGA dihapus.
     *
     * @return array{deleted: int, deactivated: int, categories: int}
     */
    public function removeOthers(ProductService $products): array
    {
        $result = ['deleted' => 0, 'deactivated' => 0, 'categories' => 0];

        Product::whereNotIn('slug', $this->slugs())->withCount('orderItems')->get()
            ->each(function (Product $product) use ($products, &$result) {
                if ($product->order_items_count > 0) {
                    if ($product->is_active || $product->is_featured) {
                        $product->update(['is_active' => false, 'is_featured' => false]);
                    }
                    $result['deactivated']++;
                } else {
                    $products->delete($product);
                    $result['deleted']++;
                }
            });

        // Kategori lama dihapus semua (subkategori dulu). Produk lama yang tersisa
        // sudah nonaktif; kategorinya otomatis jadi kosong (FK nullOnDelete).
        Category::whereNotIn('slug', $this->categorySlugs())->orderByRaw('parent_id is null')->get()
            ->each(function (Category $category) use (&$result) {
                $category->delete();
                $result['categories']++;
            });

        return $result;
    }

    /** @return list<string> */
    private function categorySlugs(): array
    {
        $slugs = [];
        foreach ($this->data()['categories'] as $category) {
            $slugs[] = $category['slug'];
            array_push($slugs, ...array_column($category['children'] ?? [], 'slug'));
        }

        return $slugs;
    }

    /** @return array<string, Category> slug => kategori */
    private function importCategories(array &$result): array
    {
        $map = [];

        foreach ($this->data()['categories'] as $order => $item) {
            $parent = $this->category($item, null, $order, $result);
            $map[$item['slug']] = $parent;

            foreach ($item['children'] ?? [] as $childOrder => $child) {
                $map[$child['slug']] = $this->category($child, $parent, $childOrder, $result);
            }
        }

        return $map;
    }

    private function category(array $item, ?Category $parent, int $order, array &$result): Category
    {
        $category = Category::firstOrCreate(['slug' => $item['slug']], [
            'parent_id' => $parent?->id,
            'name' => $item['name'],
            'description' => $item['description'] ?? null,
            'is_active' => true,
            'sort_order' => $order,
        ]);

        if ($category->wasRecentlyCreated) {
            $result['categories']++;
        }

        return $category;
    }

    private function createProduct(array $item, ?Category $category, int $initialStock): Product
    {
        $variants = $item['variants'] ?? [];
        $active = ($item['active'] ?? true) && $item['price'] > 0;

        return DB::transaction(function () use ($item, $category, $initialStock, $variants, $active) {
            $product = Product::create([
                'category_id' => $category?->id,
                'name' => $item['name'],
                'slug' => $item['slug'],
                'sku' => 'MGA-'.Str::upper(Str::substr(md5($item['slug']), 0, 6)),
                'description' => $this->description($item),
                'price' => $item['price'],
                'compare_at_price' => $this->compare($item['price'], $item['compare'] ?? null),
                'weight' => $item['weight'] ?? 500,
                'variant_label' => $variants ? ($item['variant_label'] ?? 'Pilihan') : null,
                'is_active' => $active,
                'is_featured' => $active && ($item['featured'] ?? false),
            ]);

            if (! $variants) {
                $this->stock->adjust($product, null, $active ? $initialStock : 0, StockMovementType::Initial, 'Impor katalog MGA');

                return $product;
            }

            $created = collect($variants)->values()->map(function (array $variant, int $sort) use ($product) {
                [$name, $price, $compare] = $variant + [2 => null];

                return $product->variants()->create([
                    'name' => $name,
                    'sku' => $product->sku.'-'.Str::upper(Str::slug($name, '')),
                    'price' => $price,
                    'compare_at_price' => $this->compare($price, $compare),
                    'is_active' => ($variant[3] ?? true) && $price > 0,
                    'sort_order' => $sort,
                ]);
            });

            $product->syncVariantSummary();

            foreach ($created as $variant) {
                if ($active && $variant->is_active) {
                    $this->stock->adjust($product, $variant, $initialStock, StockMovementType::Initial, 'Impor katalog MGA');
                }
            }

            return $product->refresh();
        });
    }

    private function compare(int $price, ?int $compare): ?int
    {
        return $compare && $compare > $price ? $compare : null;
    }

    private function description(array $item): string
    {
        $parts = [];

        if (! empty($item['note'])) {
            $parts[] = $item['note'];
        }
        if (! empty($item['specs'])) {
            $parts[] = "Spesifikasi:\n• ".implode("\n• ", $item['specs']);
        }
        if (! empty($item['uses'])) {
            $parts[] = "Kegunaan:\n• ".implode("\n• ", $item['uses']);
        }
        if (! ($item['active'] ?? true) || $item['price'] <= 0) {
            $parts[] = 'Harga menyusul — tanyakan stok & harga ke admin.';
        }
        if (! empty($this->data()['footer'])) {
            $parts[] = $this->data()['footer'];
        }

        return implode("\n\n", $parts);
    }
}
