<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'contact_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'street' => fake()->streetName(),
            'house_number' => (string) fake()->buildingNumber(),
            'postal_code' => '2845',
            'city' => 'Niel',
        ];
    }
}
