<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return ['customer_id' => Customer::factory(), 'issue_date' => today(), 'subject' => 'Test'];
    }

    /**
     * @param  list<array{0: string, 1: float|int, 2: float|int, 3?: float|int}>  $lines  description, quantity, price, vat
     */
    public function withLines(array $lines): static
    {
        return $this->afterCreating(function (Invoice $invoice) use ($lines): void {
            foreach ($lines as $sort => $line) {
                $invoice->lines()->create(['description' => $line[0], 'quantity' => $line[1], 'unit_price' => $line[2], 'vat_rate' => $line[3] ?? 0, 'sort' => $sort]);
            }

            $invoice->recalculate();
        });
    }
}
