<?php

namespace Database\Factories;

use App\Models\ArticleCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ArticleCategory> */
class ArticleCategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true));

        return ['name' => $name, 'slug' => Str::slug($name), 'sort_order' => 0];
    }
}
