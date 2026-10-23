<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id', 'product_image_id', 'name', 'sku', 'price', 'compare_at_price',
    'weight', 'is_active', 'sort_order',
])]
class ProductVariant extends Model
{
    use HasFactory;

    // Kolom `stock` sengaja tidak mass-assignable: ubah lewat StockService agar tercatat.

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'compare_at_price' => 'integer',
            'stock' => 'integer',
            'weight' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class, 'product_image_id');
    }

    public function effectiveWeight(): int
    {
        return $this->weight ?: $this->product->weight;
    }

    public function isOnSale(): bool
    {
        return $this->compare_at_price && $this->compare_at_price > $this->price;
    }

    public function inStock(): bool
    {
        return $this->stock > 0;
    }

    public function isLowStock(): bool
    {
        return $this->stock <= config('shop.low_stock_threshold');
    }

    public function formattedPrice(): string
    {
        return Money::rupiah($this->price);
    }
}
