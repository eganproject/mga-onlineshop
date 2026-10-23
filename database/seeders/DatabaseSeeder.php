<?php

namespace Database\Seeders;

use App\Enums\ReviewStatus;
use App\Enums\StockMovementType;
use App\Enums\UserRole;
use App\Enums\VoucherType;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Catalog\StockService;
use App\Services\Reviews\ReviewService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function __construct(private readonly StockService $stock) {}

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
                'province_id' => '2',
                'province' => 'Jawa Barat',
                'city_id' => '201',
                'city' => 'Kota Bandung',
                'district_id' => '20101',
                'district' => 'Coblong',
                'postal_code' => '40132',
                'address_line' => 'Jl. Dago No. 10, RT 02/RW 05',
                'is_default' => true,
            ]);
        }

        $this->seedCatalog();
        // Tanpa event model supaya ratusan pesanan contoh tidak mengantrekan email.
        Order::withoutEvents(fn () => $this->call(DemoOrderSeeder::class));
        $this->seedReviews();
        $this->seedVouchers();
    }

    /** Voucher contoh (banner perlu gambar, jadi diunggah sendiri lewat admin). */
    private function seedVouchers(): void
    {
        $vouchers = [
            ['code' => 'HEMAT10', 'name' => 'Diskon 10% semua produk', 'type' => VoucherType::Percent, 'value' => 10, 'max_discount' => 25000, 'min_spend' => 100000, 'per_user_limit' => 1],
            ['code' => 'ONGKIRGRATIS', 'name' => 'Gratis ongkir s.d. Rp20.000', 'type' => VoucherType::FreeShipping, 'value' => 20000, 'min_spend' => 75000, 'per_user_limit' => 3],
            ['code' => 'MGA50K', 'name' => 'Potongan Rp50.000', 'type' => VoucherType::Fixed, 'value' => 50000, 'min_spend' => 500000, 'quota' => 100, 'per_user_limit' => 1],
        ];

        foreach ($vouchers as $voucher) {
            Voucher::updateOrCreate(['code' => $voucher['code']], $voucher + ['is_active' => true, 'is_public' => true]);
        }
    }

    /** Contoh ulasan yang sudah tayang supaya tampilan rating terlihat saat demo. */
    private function seedReviews(): void
    {
        $samples = [
            [5, 'Mantap, sesuai deskripsi', 'Barang original, packing rapi pakai bubble wrap. Pengiriman cepat, besoknya sudah sampai.'],
            [4, 'Bagus', 'Kualitas oke untuk harganya. Pengiriman agak lama karena kurir, tapi seller responsif.'],
            [5, 'Recommended seller', 'Sudah beli kedua kalinya di sini, selalu puas. Admin ramah dan membantu pilih tipe yang cocok.'],
        ];

        $reviewers = collect(['Rina Wulandari', 'Agus Pratama', 'Dewi Lestari'])->map(fn ($name) => User::firstOrCreate(
            ['email' => Str::slug($name, '.').'@contoh.test'],
            ['name' => $name, 'password' => 'password', 'email_verified_at' => now()],
        ));

        $reviewService = app(ReviewService::class);

        Product::where('is_featured', true)->get()->each(function (Product $product) use ($reviewers, $samples, $reviewService) {
            foreach ($reviewers as $i => $reviewer) {
                [$rating, $title, $body] = $samples[($product->id + $i) % count($samples)];

                ProductReview::firstOrNew(['user_id' => $reviewer->id, 'product_id' => $product->id])->forceFill([
                    'user_id' => $reviewer->id,
                    'product_id' => $product->id,
                    'rating' => $rating,
                    'title' => $title,
                    'body' => $body,
                    'status' => ReviewStatus::Approved,
                    'approved_at' => now()->subDays($i * 3),
                ])->save();
            }

            $reviewService->syncSummary($product);
        });
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
                $product = Product::updateOrCreate(['slug' => Str::slug($name)], [
                    'category_id' => $this->subcategoryFor($category, $name)->id,
                    'name' => $name,
                    'sku' => 'MGA-'.Str::upper(Str::substr(md5($name), 0, 6)),
                    'description' => "{$name} berkualitas tinggi.\n\n• Produk original\n• Garansi toko 7 hari\n• Siap kirim hari ini",
                    'price' => $price,
                    'compare_at_price' => $compare,
                    'weight' => $weight,
                    'is_active' => true,
                    'is_featured' => $featured,
                ]);

                if (! $product->wasRecentlyCreated) {
                    continue;
                }

                if (isset(self::VARIANTS[$name])) {
                    [$label, $variants] = self::VARIANTS[$name];
                    $product->update(['variant_label' => $label]);

                    foreach ($variants as $position => [$variantName, $variantPrice]) {
                        $variant = $product->variants()->create([
                            'name' => $variantName,
                            'sku' => $product->sku.'-'.Str::upper(Str::slug($variantName, '')),
                            'price' => $variantPrice ?? $price,
                            'compare_at_price' => $compare ? $compare + (($variantPrice ?? $price) - $price) : null,
                            'is_active' => true,
                            'sort_order' => $position,
                        ]);
                        $this->stock->adjust($product, $variant, random_int(0, 5) === 0 ? 0 : random_int(5, 40), StockMovementType::Initial);
                    }
                } else {
                    $this->stock->adjust($product, null, random_int(0, 4) === 0 ? random_int(1, 4) : random_int(10, 80), StockMovementType::Initial);
                }
            }
        }
    }

    /** Contoh varian: nama produk => [judul pilihan, [[nama, harga|null]]]. */
    private const VARIANTS = [
        'Casing iPhone 15 Clear MagSafe' => ['Tipe HP', [['iPhone 15', null], ['iPhone 15 Plus', null], ['iPhone 15 Pro', 99000], ['iPhone 15 Pro Max', 99000]]],
        'Casing Samsung A55 Shockproof' => ['Warna', [['Hitam', null], ['Biru Navy', null], ['Transparan', null]]],
        'Kabel USB-C to USB-C 100W 1M' => ['Panjang', [['1 meter', null], ['2 meter', 79000]]],
        'TWS Earbuds ANC Pro' => ['Warna', [['Hitam', null], ['Putih', null]]],
    ];

    /** Produk casing masuk ke subkategori per merek. */
    private function subcategoryFor(Category $category, string $productName): Category
    {
        if ($category->slug !== 'casing-hp') {
            return $category;
        }

        $brand = collect(['iPhone', 'Samsung', 'Xiaomi'])->first(fn ($b) => str_contains($productName, $b)) ?? 'Lainnya';

        return Category::updateOrCreate(['slug' => 'casing-'.Str::slug($brand)], [
            'parent_id' => $category->id,
            'name' => "Casing {$brand}",
            'is_active' => true,
        ]);
    }
}
