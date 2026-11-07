<?php

namespace Database\Factories;

use App\Enums\AnnualLine;
use App\Models\AnnualEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnnualEntry>
 */
class AnnualEntryFactory extends Factory
{
    public function definition(): array
    {
        return ['year' => now()->year, 'line' => AnnualLine::Subsidy, 'description' => 'Subsidie gemeente', 'amount' => 1500];
    }
}
