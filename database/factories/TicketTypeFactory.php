<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketType>
 */
class TicketTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory()->state(['tickets_enabled' => true]),
            'name' => 'Voorverkoop',
            'price' => 8,
            'capacity' => null,
            'max_per_order' => 10,
            'is_active' => true,
        ];
    }
}
