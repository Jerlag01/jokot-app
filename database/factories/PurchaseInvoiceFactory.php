<?php

namespace Database\Factories;

use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseInvoice>
 */
class PurchaseInvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'number' => (string) fake()->unique()->numberBetween(1000, 99999),
            'issue_date' => today(),
            'due_date' => today()->addDays(30),
            'subtotal' => 100,
            'vat_total' => 21,
            'total' => 121,
        ];
    }
}
