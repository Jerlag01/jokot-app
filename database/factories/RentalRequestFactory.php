<?php

namespace Database\Factories;

use App\Enums\RentalStatus;
use App\Models\RentalRate;
use App\Models\RentalRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RentalRequest>
 */
class RentalRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'status' => RentalStatus::New,
            'event_date' => now()->addDays(fake()->numberBetween(40, 150))->toDateString(),
            'rental_rate_id' => RentalRate::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->safeEmail(),
            'phone' => '0470 12 34 56',
            'street' => fake()->streetName(),
            'house_number' => (string) fake()->buildingNumber(),
            'postal_code' => '2845',
            'city' => 'Niel',
            'expected_guests' => 80,
            'brings_own_wine' => false,
            'terms_accepted_at' => now(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(['status' => RentalStatus::Confirmed]);
    }
}
