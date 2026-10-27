<?php

namespace Database\Seeders;

use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Enums\VoucherType;
use App\Models\Order;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Billing\InvoiceNumberer;
use App\Services\Catalog\MgaCatalog;
use App\Services\Catalog\SoldCountService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Data contoh (akun berpassword "password", pesanan palsu) tidak boleh masuk server produksi.
        if (app()->isProduction()) {
            $this->command?->error('Seeder contoh tidak dijalankan di produksi. Buat akun Superadmin dengan: php artisan shop:create-admin');

            return;
        }

        User::updateOrCreate(['email' => 'admin@mga.test'], [
            'name' => 'Admin MGA',
            'password' => 'password',
            'phone' => '081200000000',
            'role' => UserRole::Admin,
            'staff_role' => StaffRole::Owner,
            'email_verified_at' => now(),
        ]);

        // Contoh staf dengan akses terbatas.
        foreach ([
            ['gudang@mga.test', 'Rudi Hartono', StaffRole::Warehouse],
            ['cs@mga.test', 'Sari Wulandari', StaffRole::CustomerService],
        ] as [$email, $name, $staffRole]) {
            User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => 'password',
                'role' => UserRole::Admin,
                'staff_role' => $staffRole,
                'email_verified_at' => now(),
            ]);
        }

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
        app(InvoiceNumberer::class)->backfill();
        $this->call(DemoReviewSeeder::class);
        app(SoldCountService::class)->sync();
        $this->seedVouchers();
        $this->call(ArticleSeeder::class);
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

    /** Katalog asli MGA tanpa foto (foto diunduh lewat `php artisan shop:import-mga --images`). */
    private function seedCatalog(): void
    {
        $result = app(MgaCatalog::class)->import(withImages: false, initialStock: 25);

        $this->command?->info("Katalog MGA: {$result['created']} produk baru. Unduh fotonya dengan: php artisan shop:import-mga --images");
    }
}
