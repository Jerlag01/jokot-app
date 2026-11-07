<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => ucfirst(fake()->word()), 'package' => 'bak van 24 flesjes', 'bar_price' => null, 'is_orderable' => true, 'sort' => 0, 'is_active' => true];
    }
}
