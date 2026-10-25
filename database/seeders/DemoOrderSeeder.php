<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Riwayat pesanan contoh (±4 bulan) agar dashboard & laporan punya data saat
 * development. Tidak mengubah stok, dan dilewati bila sudah ada pesanan atau
 * berjalan di production.
 */
class DemoOrderSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction() || Order::exists()) {
            return;
        }

        $products = Product::with('activeVariants')->where('is_active', true)->get();
        if ($products->isEmpty()) {
            return;
        }

        $customers = User::where('role', UserRole::Customer)->get();
        foreach (['Siti Rahma', 'Andi Pratama', 'Dewi Lestari', 'Rizky Hidayat', 'Nur Aisyah', 'Fajar Nugroho', 'Rina Wulandari', 'Agus Setiawan', 'Maya Kartika', 'Hendra Gunawan', 'Lina Marlina', 'Yusuf Maulana'] as $i => $name) {
            $customers->push(User::firstWhere('email', 'demo'.($i + 1).'@mga.test') ?? User::factory()->create([
                'name' => $name,
                'email' => 'demo'.($i + 1).'@mga.test',
                'created_at' => now()->subDays(random_int(1, 150)),
            ]));
        }
        $customers = $customers->unique('id')->values();

        DB::transaction(function () use ($products, $customers) {
            for ($daysAgo = 120; $daysAgo >= 0; $daysAgo--) {
                // Sedikit tren naik + akhir pekan lebih ramai.
                $day = today()->subDays($daysAgo);
                $base = 1 + (120 - $daysAgo) / 60 + ($day->isWeekend() ? 1 : 0);
                $count = random_int(0, (int) round($base * 1.5));

                for ($n = 0; $n < $count; $n++) {
                    $this->createOrder($customers->random(), $products, $daysAgo === 0 ? now()->subMinutes(random_int(5, 360)) : $day->copy()->setTime(random_int(7, 22), random_int(0, 59)), $daysAgo);
                }
            }
        });
    }

    private function createOrder(User $customer, $products, $at, int $daysAgo): void
    {
        $status = match (true) {
            random_int(1, 100) <= 7 => OrderStatus::Cancelled,
            $daysAgo === 0 => fake()->randomElement([OrderStatus::PendingPayment, OrderStatus::Paid, OrderStatus::Paid]),
            $daysAgo <= 2 => fake()->randomElement([OrderStatus::Paid, OrderStatus::Processing]),
            $daysAgo <= 6 => fake()->randomElement([OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered]),
            default => OrderStatus::Completed,
        };

        $lines = $products->random(min($products->count(), random_int(1, 3)))->map(function (Product $product) {
            $variant = $product->has_variants ? $product->activeVariants->random() : null;
            $price = $variant?->price ?? $product->price;
            $qty = random_int(1, 3);

            return [
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'product_name' => $product->name,
                'variant_name' => $variant?->name,
                'sku' => $variant?->sku ?? $product->sku,
                'price' => $price,
                'quantity' => $qty,
                'weight' => $product->weight,
                'subtotal' => $price * $qty,
            ];
        });

        $subtotal = $lines->sum('subtotal');
        $shipping = fake()->randomElement([9000, 12000, 15000, 22000]);

        $order = Order::factory()->for($customer)->create([
            'status' => $status,
            'subtotal' => $subtotal,
            'shipping_cost' => $shipping,
            'total' => $subtotal + $shipping,
            'recipient_name' => $customer->name,
            'courier' => fake()->randomElement(['jne', 'sicepat', 'jnt']),
            'tracking_number' => $status->isTrackable() ? 'DEMO'.random_int(100000, 999999) : null,
            'created_at' => $at,
            'updated_at' => $at,
            'paid_at' => in_array($status, [OrderStatus::PendingPayment, OrderStatus::Cancelled], true) ? null : $at->copy()->addMinutes(random_int(1, 5)),
            'shipped_at' => $status->isTrackable() ? $at->copy()->addDay() : null,
            'delivered_at' => in_array($status, [OrderStatus::Delivered, OrderStatus::Completed], true) ? $at->copy()->addDays(3) : null,
            'completed_at' => $status === OrderStatus::Completed ? $at->copy()->addDays(4) : null,
            'cancelled_at' => $status === OrderStatus::Cancelled ? $at->copy()->addHours(3) : null,
        ]);

        $order->items()->createMany($lines->all());
        $order->rebuildStatusHistory();
    }
}
