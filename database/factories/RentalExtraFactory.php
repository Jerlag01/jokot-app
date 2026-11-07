<?php

namespace Database\Factories;

use App\Models\RentalExtra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RentalExtra>
 */
class RentalExtraFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => 'DJ Installatie '.fake()->word(), 'price' => 225, 'deposit' => 75, 'sort' => 0, 'is_active' => true];
    }
}
