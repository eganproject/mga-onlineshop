<?php

namespace Database\Factories;

use App\Models\Banner;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Banner> */
class BannerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'subtitle' => fake()->sentence(),
            'button_label' => 'Belanja sekarang',
            'link_url' => '/produk',
            'image_path' => 'banners/contoh.jpg',
            'show_text' => true,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
