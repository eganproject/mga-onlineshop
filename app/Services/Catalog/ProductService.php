<?php

namespace App\Services\Catalog;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Simpan produk beserta galeri foto, varian, dan perubahan stoknya dalam satu transaksi. */
class ProductService
{
    private const FORM_NOTE = 'Diubah dari form produk';

    public function __construct(private readonly StockService $stock) {}

    /**
     * @param  array<string, mixed>  $data  Data tervalidasi dari ProductRequest.
     * @param  list<UploadedFile>  $uploads
     */
    public function save(Product $product, array $data, array $uploads = []): Product
    {
        return DB::transaction(function () use ($product, $data, $uploads) {
            $isNew = ! $product->exists;
            $hasVariants = (bool) ($data['has_variants'] ?? false);

            $product->fill(Arr::only($data, ['category_id', 'name', 'sku', 'description', 'meta_title', 'meta_description', 'weight']));
            $product->min_order_qty = max(1, (int) ($data['min_order_qty'] ?? 1));
            $product->slug = ($data['slug'] ?? null) ?: $this->uniqueSlug($data['name'], $product);
            $product->is_active = (bool) ($data['is_active'] ?? false);
            $product->is_featured = (bool) ($data['is_featured'] ?? false);
            $product->variant_label = $hasVariants ? ($data['variant_label'] ?? null) : null;

            if ($hasVariants) {
                // Sementara pakai harga varian termurah; disinkronkan lagi setelah varian tersimpan.
                $product->price ??= (int) collect($data['variants'])->min('price');
            } else {
                $product->price = $data['price'];
                $product->compare_at_price = $data['compare_at_price'] ?? null;
                $product->wholesale_tiers = $data['wholesale_tiers'] ?? [];
            }

            $product->save();

            $this->syncImages($product, $data['image_order'] ?? [], $data['remove_images'] ?? [], $uploads);

            if ($hasVariants) {
                $this->syncVariants($product, $data['variants']);
            } else {
                $dropped = $this->dropVariants($product);
                $this->syncProductStock($product, $isNew || $dropped, (int) $data['stock'], $data['stock_original'] ?? null);
            }

            return $product->refresh();
        });
    }

    /** Hapus produk beserta file fotonya. */
    public function delete(Product $product): void
    {
        DB::transaction(function () use ($product) {
            $product->images()->get()->each->delete();
            $product->delete();
        });
    }

    private function syncImages(Product $product, array $order, array $remove, array $uploads): void
    {
        $product->images()->whereKey($remove)->get()->each->delete();

        $ids = $product->images()->pluck('id');
        // Urutan dari form dulu, sisanya (kalau ada) di belakang.
        $ordered = collect($order)->map(fn ($id) => (int) $id)->intersect($ids)->unique()->merge($ids)->unique()->values();

        foreach ($ordered as $position => $id) {
            ProductImage::whereKey($id)->update(['sort_order' => $position]);
        }

        $position = $ordered->count();
        foreach ($uploads as $file) {
            $product->images()->create([
                'path' => $file->store('products', 'public'),
                'sort_order' => $position++,
            ]);
        }

        $product->unsetRelation('coverImage')->unsetRelation('images');
    }

    private function syncVariants(Product $product, array $rows): void
    {
        $existing = $product->variants()->get()->keyBy('id');
        $imageIds = $product->images()->pluck('id');
        $keep = [];

        // Hapus varian yang dibuang lebih dulu agar SKU-nya bisa dipakai ulang.
        $submittedIds = collect($rows)->pluck('id')->filter()->map(fn ($id) => (int) $id);
        $existing->except($submittedIds->all())->each->delete();

        foreach (array_values($rows) as $position => $row) {
            /** @var ProductVariant $variant */
            $variant = $existing->get((int) ($row['id'] ?? 0)) ?? new ProductVariant(['product_id' => $product->id]);
            $isNew = ! $variant->exists;

            $variant->fill([
                'name' => trim($row['name']),
                'sku' => filled($row['sku'] ?? null) ? trim($row['sku']) : null,
                'price' => (int) $row['price'],
                'compare_at_price' => filled($row['compare_at_price'] ?? null) ? (int) $row['compare_at_price'] : null,
                'weight' => filled($row['weight'] ?? null) ? (int) $row['weight'] : null,
                'product_image_id' => $imageIds->contains((int) ($row['image_id'] ?? 0)) ? (int) $row['image_id'] : null,
                'is_active' => (bool) ($row['is_active'] ?? false),
                'wholesale_tiers' => $row['wholesale_tiers'] ?? [],
                'sort_order' => $position,
            ]);
            $variant->save();
            $variant->setRelation('product', $product);
            $keep[] = $variant->id;

            $stock = (int) $row['stock'];
            $original = isset($row['stock_original']) ? (int) $row['stock_original'] : null;

            if ($isNew) {
                $this->stock->adjust($product, $variant, $stock, StockMovementType::Initial);
            } elseif ($original === null || $stock !== $original) {
                // Hanya ubah kalau admin memang mengganti angkanya, supaya penjualan
                // yang terjadi selama form terbuka tidak tertimpa.
                $this->stock->set($product, $variant, $stock, StockMovementType::Adjustment, self::FORM_NOTE);
            }
        }

        $product->syncVariantSummary();
    }

    /** Matikan mode varian. Mengembalikan true bila sebelumnya produk punya varian. */
    private function dropVariants(Product $product): bool
    {
        if (! $product->has_variants && ! $product->variants()->exists()) {
            return false;
        }

        $product->variants()->get()->each->delete();
        $product->syncVariantSummary();

        return true;
    }

    /** @param  bool  $force  true untuk produk baru / baru keluar dari mode varian. */
    private function syncProductStock(Product $product, bool $force, int $stock, mixed $original): void
    {
        if (! $product->wasRecentlyCreated && ! $force && $original !== null && $stock === (int) $original) {
            return;
        }

        $type = $product->wasRecentlyCreated ? StockMovementType::Initial : StockMovementType::Adjustment;
        $this->stock->set($product, null, $stock, $type, $type === StockMovementType::Adjustment ? self::FORM_NOTE : null);
    }

    public function uniqueSlug(string $name, Product $ignore): string
    {
        $base = Str::slug($name) ?: 'produk';
        $slug = $base;
        $i = 2;

        while (Product::where('slug', $slug)->when($ignore->exists, fn ($q) => $q->whereKeyNot($ignore->id))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
