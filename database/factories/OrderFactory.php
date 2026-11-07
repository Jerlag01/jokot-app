<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Models\Event;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory()->state(['tickets_enabled' => true]),
            'status' => OrderStatus::Pending,
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'total' => 16,
            'provider' => PaymentProvider::Stripe,
            'expires_at' => now()->addMinutes(30),
        ];
    }
}
