<?php

namespace App\Services\Reports;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

/**
 * Daftar item aktif yang stoknya di bawah/sama dengan batas stok menipis.
 * Produk bervarian dicek per varian, karena total stok bisa terlihat aman
 * padahal satu varian sudah habis.
 */
class LowStockReport
{
    public function threshold(): int
    {
        return (int) config('shop.low_stock_threshold');
    }

    /** Jumlah item (produk tanpa varian + varian) yang stoknya menipis/habis. */
    public function count(): int
    {
        return $this->simpleProducts()->count() + $this->variants()->count();
    }

    /**
     * @return Collection<int, object{product:Product, variant:?ProductVariant, name:string, sku:?string, stock:int}>
     */
    public function items(int $limit = 6): Collection
    {
        $products = $this->simpleProducts()->orderBy('stock')->orderBy('name')->take($limit)->get()
            ->map(fn (Product $p) => (object) ['product' => $p, 'variant' => null, 'name' => $p->name, 'sku' => $p->sku, 'stock' => $p->stock]);

        $variants = $this->variants()->with('product')->orderBy('stock')->take($limit)->get()
            ->map(fn (ProductVariant $v) => (object) ['product' => $v->product, 'variant' => $v, 'name' => $v->product->name, 'sku' => $v->sku, 'stock' => $v->stock]);

        return $products->concat($variants)
            ->sortBy([['stock', 'asc'], ['name', 'asc']])
            ->take($limit)
            ->values();
    }

    private function simpleProducts()
    {
        return Product::query()
            ->where('is_active', true)
            ->where('has_variants', false)
            ->where('stock', '<=', $this->threshold());
    }

    private function variants()
    {
        return ProductVariant::query()
            ->where('is_active', true)
            ->where('stock', '<=', $this->threshold())
            ->whereHas('product', fn ($q) => $q->where('is_active', true)->where('has_variants', true));
    }
}
