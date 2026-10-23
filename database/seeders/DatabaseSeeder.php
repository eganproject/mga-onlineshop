<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email' => 'admin@mga.test'], [
            'name' => 'Admin MGA',
            'password' => 'password',
            'phone' => '081200000000',
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
        ]);

        $customer = User::updateOrCreate(['email' => 'pelanggan@mga.test'], [
            'name' => 'Budi Santoso',
            'password' => 'password',
            'phone' => '081311112222',
            'role' => UserRole::Customer,
            'email_verified_at' => now(),
        ]);

        if (! $customer->addresses()->exists()) {
            $customer->addresses()->create([
                'label' => 'Rumah',
                'recipient_name' => 'Budi Santoso',
                'phone' => '081311112222',
                'province' => 'Jawa Barat',
                'city' => 'Kota Bandung',
                'district' => 'Coblong',
                'postal_code' => '40132',
                'address_line' => 'Jl. Dago No. 10, RT 02/RW 05',
                'destination_id' => '2001',
                'is_default' => true,
            ]);
        }

        $this->seedCatalog();
    }

    private function seedCatalog(): void
    {
        $catalog = [
            'Casing HP' => [
                ['Casing iPhone 15 Clear MagSafe', 89000, 129000, 120, true],
                ['Casing Samsung A55 Shockproof', 65000, null, 80, false],
                ['Casing Xiaomi Redmi Note 13 Matte', 45000, 60000, 150, false],
            ],
            'Charger & Adaptor' => [
                ['Charger GaN 65W 3 Port', 249000, 329000, 200, true],
                ['Adaptor Fast Charging 25W USB-C', 99000, null, 180, false],
            ],
            'Kabel Data' => [
                ['Kabel USB-C to USB-C 100W 1M', 59000, 79000, 60, true],
                ['Kabel Lightning Braided 1.2M', 49000, null, 100, false],
            ],
            'Earphone & Headset' => [
                ['TWS Earbuds ANC Pro', 399000, 549000, 300, true],
                ['Earphone Kabel Bass Boost', 39000, null, 50, false],
            ],
            'Powerbank' => [
                ['Powerbank 10000mAh Slim PD 20W', 189000, 239000, 250, true],
                ['Powerbank 20000mAh Fast Charge', 279000, null, 450, false],
            ],
            'Tempered Glass' => [
                ['Tempered Glass Anti-Spy iPhone 15', 35000, 50000, 30, false],
                ['Tempered Glass Full Cover Samsung S24', 29000, null, 30, true],
            ],
            'Holder & Stand' => [
                ['Holder HP Mobil Magnetic', 75000, 99000, 150, false],
                ['Stand HP & Tablet Lipat Aluminium', 85000, null, 300, false],
            ],
        ];

        $order = 0;
        foreach ($catalog as $categoryName => $products) {
            $category = Category::updateOrCreate(['slug' => Str::slug($categoryName)], [
                'name' => $categoryName,
                'description' => "Koleksi {$categoryName} pilihan MGA.",
                'is_active' => true,
                'sort_order' => $order++,
            ]);

            foreach ($products as [$name, $price, $compare, $weight, $featured]) {
                Product::updateOrCreate(['slug' => Str::slug($name)], [
                    'category_id' => $category->id,
                    'name' => $name,
                    'sku' => 'MGA-'.Str::upper(Str::substr(md5($name), 0, 6)),
                    'description' => "{$name} berkualitas tinggi.\n\n• Produk original\n• Garansi toko 7 hari\n• Siap kirim hari ini",
                    'price' => $price,
                    'compare_at_price' => $compare,
                    'stock' => random_int(0, 4) === 0 ? random_int(1, 4) : random_int(10, 80),
                    'weight' => $weight,
                    'is_active' => true,
                    'is_featured' => $featured,
                ]);
            }
        }
    }
}
