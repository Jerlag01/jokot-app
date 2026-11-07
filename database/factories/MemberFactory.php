<?php

namespace Database\Factories;

use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'street' => fake()->streetName(),
            'house_number' => '12',
            'postal_code' => '2845',
            'city' => 'Niel',
            'email' => fake()->safeEmail(),
            'phone' => '0470 12 34 56',
        ];
    }

    public function memberIn(int $year, float $amount = 5): static
    {
        return $this->afterCreating(fn (Member $member) => $member->memberships()->create(['year' => $year, 'amount' => $amount]));
    }
}
