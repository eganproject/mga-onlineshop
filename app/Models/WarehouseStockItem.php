<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Salinan stok terakhir satu SKU dari API warehouse29. */
#[Fillable(['sku', 'name', 'category', 'uom', 'qty', 'status', 'warehouse_updated_at'])]
class WarehouseStockItem extends Model
{
    protected function casts(): array
    {
        return [
            'qty' => 'float',
            'warehouse_updated_at' => 'datetime',
        ];
    }

    /** Stok layak jual di gudang (barang nonaktif/dihapus = 0, bundle dibulatkan ke bawah). */
    public function sellableQty(): int
    {
        return $this->status === 'active' ? max(0, (int) floor($this->qty)) : 0;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'active' => 'Aktif',
            'inactive' => 'Nonaktif di gudang',
            'deleted' => 'Dihapus di gudang',
            default => $this->status,
        };
    }

    /**
     * SKU yang belum terhubung ke produk/varian mana pun di toko, baik lewat
     * `warehouse_sku` maupun SKU toko yang sama persis.
     */
    public function scopeUnlinked(Builder $query): Builder
    {
        // SKU toko hanya dipakai bila produk/varian itu belum punya warehouse_sku.
        $products = fn (string $column) => fn ($q) => $q->select($column)->from('products')->where('has_variants', false)->whereNotNull($column)
            ->when($column === 'sku', fn ($q) => $q->whereNull('warehouse_sku'));
        $variants = fn (string $column) => fn ($q) => $q->select("product_variants.{$column}")->from('product_variants')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('products.has_variants', true)->whereNotNull("product_variants.{$column}")
            ->when($column === 'sku', fn ($q) => $q->whereNull('product_variants.warehouse_sku'));

        return $query
            ->whereNotIn('sku', $products('warehouse_sku'))
            ->whereNotIn('sku', $products('sku'))
            ->whereNotIn('sku', $variants('warehouse_sku'))
            ->whereNotIn('sku', $variants('sku'));
    }
}
