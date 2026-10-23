<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Address> */
class AddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => 'Rumah',
            'recipient_name' => fake()->name(),
            'phone' => fake()->numerify('08##########'),
            'province_id' => '2',
            'province' => 'Jawa Barat',
            'city_id' => '201',
            'city' => 'Kota Bandung',
            'district_id' => '20101',
            'district' => 'Coblong',
            'postal_code' => '40132',
            'address_line' => fake()->streetAddress(),
            'is_default' => true,
        ];
    }

    /** Alamat lama yang belum memilih wilayah dari daftar (ongkir belum bisa dihitung). */
    public function withoutRegion(): static
    {
        return $this->state(['province_id' => null, 'city_id' => null, 'district_id' => null]);
    }
}
