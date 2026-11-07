<?php

namespace Database\Factories;

use App\Models\CupLoan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CupLoan>
 */
class CupLoanFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(5, 30))->startOfDay();

        return [
            'organisation' => fake()->company(),
            'representative' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => '0470 12 34 56',
            'street' => fake()->streetName(),
            'house_number' => '1',
            'postal_code' => '2845',
            'city' => 'Niel',
            'starts_on' => $start->toDateString(),
            'ends_on' => $start->copy()->addDays(2)->toDateString(),
            'cups' => 200,
            'crates' => 4,
        ];
    }
}
