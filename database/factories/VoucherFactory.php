<?php

namespace Database\Factories;

use App\Enums\VoucherType;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Voucher> */
class VoucherFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'V'.Str::upper(Str::random(7)),
            'name' => 'Potongan belanja',
            'type' => VoucherType::Fixed,
            'value' => 10000,
            'min_spend' => 0,
            'is_active' => true,
            'is_public' => false,
        ];
    }

    public function percent(int $value = 10, ?int $max = null): static
    {
        return $this->state(['type' => VoucherType::Percent, 'value' => $value, 'max_discount' => $max]);
    }

    public function freeShipping(int $cap = 0): static
    {
        return $this->state(['type' => VoucherType::FreeShipping, 'value' => $cap]);
    }
}
