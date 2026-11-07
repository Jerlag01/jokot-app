<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->company(), 'iban' => 'BE71 0961 2345 6769', 'bic' => 'GKCCBEBB', 'payment_days' => 30];
    }
}
