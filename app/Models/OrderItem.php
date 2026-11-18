<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'product_id', 'product_variant_id', 'product_name', 'variant_name', 'sku', 'price', 'regular_price', 'list_price', 'quantity', 'weight', 'subtotal'])]
class OrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'regular_price' => 'integer',
            'list_price' => 'integer',
            'quantity' => 'integer',
            'weight' => 'integer',
            'subtotal' => 'integer',
        ];
    }

    /** Baris ini dibeli dengan harga grosir. */
    public function isWholesale(): bool
    {
        return $this->regular_price !== null && $this->regular_price > $this->price;
    }

    /** Harga baris diubah manual oleh staf (penjualan manual); list_price = harga web saat itu. */
    public function hasCustomPrice(): bool
    {
        return $this->list_price !== null && $this->list_price !== $this->price;
    }

    /** Selisih harga manual terhadap harga web untuk seluruh jumlah (negatif = lebih murah). */
    public function customPriceDifference(): int
    {
        return $this->hasCustomPrice() ? ($this->price - $this->list_price) * $this->quantity : 0;
    }

    /** Hemat dari harga grosir untuk seluruh jumlah di baris ini. */
    public function wholesaleSavings(): int
    {
        return $this->isWholesale() ? ($this->regular_price - $this->price) * $this->quantity : 0;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
