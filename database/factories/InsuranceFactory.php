<?php

namespace Database\Factories;

use App\Enums\InsuranceType;
use App\Models\Insurance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Insurance>
 */
class InsuranceFactory extends Factory
{
    public function definition(): array
    {
        return ['type' => InsuranceType::Liability, 'name' => 'BA + LO', 'insurer' => 'Ethias', 'policy_number' => '45.123.456', 'renewal_date' => today()->addMonths(6), 'premium' => 82.57];
    }
}
