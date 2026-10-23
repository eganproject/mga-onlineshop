<?php

namespace App\Services\Cart;

use App\Models\Product;
use App\Models\ProductVariant;

final readonly class CartLine
{
    public function __construct(
        public Product $product,
        public int $quantity,
        public ?ProductVariant $variant = null,
    ) {}

    /** Kunci baris di session: "12" untuk produk biasa, "12-5" untuk varian. */
    public function key(): string
    {
        return CartService::key($this->product->id, $this->variant?->id);
    }

    /** Nama untuk ditampilkan, contoh: "Casing iPhone 15 (Hitam)". */
    public function name(): string
    {
        return $this->variant ? "{$this->product->name} ({$this->variant->name})" : $this->product->name;
    }

    public function isLowStock(): bool
    {
        return $this->availableStock() <= config('shop.low_stock_threshold');
    }

    public function unitPrice(): int
    {
        return $this->variant?->price ?? $this->product->price;
    }

    public function compareAtPrice(): ?int
    {
        return $this->variant ? $this->variant->compare_at_price : $this->product->compare_at_price;
    }

    public function unitWeight(): int
    {
        return $this->variant?->weight ?: $this->product->weight;
    }

    public function availableStock(): int
    {
        return $this->variant?->stock ?? $this->product->stock;
    }

    public function sku(): ?string
    {
        return $this->variant?->sku ?: $this->product->sku;
    }

    public function imageUrl(): ?string
    {
        return $this->variant?->image?->url() ?? $this->product->imageUrl();
    }

    public function subtotal(): int
    {
        return $this->unitPrice() * $this->quantity;
    }

    public function weight(): int
    {
        return $this->unitWeight() * $this->quantity;
    }
}
