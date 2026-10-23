<?php

namespace App\Services\Cart;

use App\Models\Product;

final readonly class CartLine
{
    public function __construct(
        public Product $product,
        public int $quantity,
    ) {}

    public function subtotal(): int
    {
        return $this->product->price * $this->quantity;
    }

    public function weight(): int
    {
        return $this->product->weight * $this->quantity;
    }
}
