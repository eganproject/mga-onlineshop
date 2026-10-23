<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ProductVariant> */
class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => Str::title(fake()->unique()->colorName()),
            'sku' => Str::upper(Str::random(10)),
            'price' => fake()->numberBetween(10, 500) * 1000,
            'compare_at_price' => null,
            'stock' => fake()->numberBetween(5, 50),
            'weight' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function configure(): static
    {
        // Jaga ringkasan harga/stok produk induk tetap sinkron.
        return $this->afterCreating(fn (ProductVariant $variant) => $variant->product->syncVariantSummary());
    }
}
