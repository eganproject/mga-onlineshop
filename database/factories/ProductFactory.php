<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(3, true));

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'sku' => Str::upper(Str::random(8)),
            'description' => fake()->paragraph(),
            'price' => fake()->numberBetween(10, 500) * 1000,
            'compare_at_price' => null,
            'stock' => fake()->numberBetween(10, 100),
            'weight' => fake()->numberBetween(50, 800),
            'is_active' => true,
            'is_featured' => false,
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => ['stock' => 0]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
