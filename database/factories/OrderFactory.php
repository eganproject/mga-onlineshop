<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(5, 100) * 5000;
        $shipping = fake()->randomElement([9000, 12000, 18000, 24000]);

        return [
            'code' => 'MGA-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
            'user_id' => User::factory(),
            'status' => OrderStatus::Paid,
            'subtotal' => $subtotal,
            'shipping_cost' => $shipping,
            'total' => $subtotal + $shipping,
            'total_weight' => fake()->numberBetween(100, 2000),
            'recipient_name' => fake()->name(),
            'recipient_phone' => '08'.fake()->numerify('##########'),
            'shipping_address' => fake()->address(),
            'courier' => 'jne',
            'courier_service' => 'REG',
            'paid_at' => now(),
        ];
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'paid_at' => $status === OrderStatus::PendingPayment ? null : now(),
            'cancelled_at' => $status === OrderStatus::Cancelled ? now() : null,
        ]);
    }

    public function unpaid(): static
    {
        return $this->status(OrderStatus::PendingPayment);
    }

    /** Pesanan dibayar pada waktu tertentu (created_at mengikuti). */
    public function paidAt(\DateTimeInterface|string $at): static
    {
        return $this->state(fn () => ['paid_at' => $at, 'created_at' => $at]);
    }
}
