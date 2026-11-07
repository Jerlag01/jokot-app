<?php

namespace Database\Factories;

use App\Models\RentalRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RentalRate>
 */
class RentalRateFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->words(3, true), 'price' => 150, 'deposit' => 200, 'sort' => 0, 'is_active' => true];
    }
}
