<?php

namespace Database\Factories;

use App\Models\BarSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BarSession>
 */
class BarSessionFactory extends Factory
{
    public function definition(): array
    {
        return ['date' => today(), 'opening_cash' => 100, 'closing_cash' => 300, 'card_total' => 50];
    }
}
