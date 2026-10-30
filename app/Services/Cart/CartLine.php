<?php

namespace App\Services\Cart;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Catalog\Wholesale;

final readonly class CartLine
{
    public function __construct(
        public Product $product,
        public int $quantity,
        public ?ProductVariant $variant = null,
        // Jumlah gabungan semua baris produk yang sama (dasar tingkat harga grosir).
        public ?int $productQuantity = null,
    ) {}

    /** Kunci baris di session: "12" untuk produk biasa, "12-5" untuk varian. */
    public function key(): string
    {
        return CartService::key($this->product->id, $this->variant?->id);
    }

    /** Nama untuk ditampilkan, contoh: "Brosur Tempel Akrilik A4 (Portrait)". */
    public function name(): string
    {
        return $this->variant ? "{$this->product->name} ({$this->variant->name})" : $this->product->name;
    }

    public function isLowStock(): bool
    {
        return $this->availableStock() <= config('shop.low_stock_threshold');
    }

    /** Harga per pcs yang dibayar (harga grosir bila jumlah gabungan mencapai tingkatnya). */
    public function unitPrice(): int
    {
        return $this->wholesaleTier()['price'] ?? $this->regularPrice();
    }

    /** Harga normal per pcs (tanpa grosir). */
    public function regularPrice(): int
    {
        return Wholesale::regularPrice($this->product, $this->variant);
    }

    public function productQuantity(): int
    {
        return $this->productQuantity ?? $this->quantity;
    }

    /** @return list<array{min_qty: int, price: int}> */
    public function wholesaleTiers(): array
    {
        return Wholesale::tiers($this->product, $this->variant);
    }

    /** @return array{min_qty: int, price: int}|null */
    public function wholesaleTier(): ?array
    {
        return Wholesale::tierFor($this->wholesaleTiers(), $this->productQuantity());
    }

    public function isWholesale(): bool
    {
        return $this->wholesaleTier() !== null;
    }

    /**
     * Tingkat grosir berikutnya + kekurangan pcs-nya, untuk ajakan di keranjang.
     * Hanya bila kekurangannya ≤ Wholesale::NUDGE_WITHIN pcs (sudah dekat).
     */
    public function nextWholesaleTier(): ?array
    {
        $next = Wholesale::nextTier($this->wholesaleTiers(), $this->productQuantity());
        $need = $next ? $next['min_qty'] - $this->productQuantity() : null;

        return $next && $need <= Wholesale::NUDGE_WITHIN ? [...$next, 'need' => $need] : null;
    }

    /** Hemat karena harga grosir untuk baris ini. */
    public function wholesaleSavings(): int
    {
        return ($this->regularPrice() - $this->unitPrice()) * $this->quantity;
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
