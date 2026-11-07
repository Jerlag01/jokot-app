<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(5, 60))->setTime(20, 0);

        return [
            'title' => ucfirst(fake()->words(2, true)),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHours(6),
            'summary' => fake()->sentence(),
            'is_published' => true,
            'blocks_rentals' => true,
        ];
    }
}
