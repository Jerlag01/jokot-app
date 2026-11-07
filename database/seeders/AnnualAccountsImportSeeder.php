<?php

namespace Database\Seeders;

use App\Enums\AnnualLine as L;
use App\Models\AnnualBudget;
use App\Models\AnnualEntry;
use Illuminate\Database\Seeder;

/**
 * One-off import of "Jaarrekening in progress.xlsx" (sheet "Overzicht Jaarrekening", as of 6 November 2026):
 * the actual figures 2024-2026 become manual entries flagged as import, the budgets 2025-2027 become budgets.
 * Runs only once (skipped when imported entries exist).
 */
class AnnualAccountsImportSeeder extends Seeder
{
    public const SOURCE = 'Overgenomen uit Jaarrekening in progress.xlsx';

    public function run(): void
    {
        if (AnnualEntry::query()->where('is_import', true)->exists()) {
            $this->command?->warn('Excel-cijfers waren al geïmporteerd, niets gedaan.');

            return;
        }

        // line => [2024 actual, 2025 budget, 2025 actual, 2026 budget, 2026 actual, 2027 budget]
        $figures = [
            L::Internet->value => [0, 750, 0, 1000, 0, 1000],
            L::RentUtilities->value => [0, 10000, 0, 3600, 0, 3500],
            L::MaintenanceContracts->value => [0, 0, 0, 100, 0, 100],
            L::MaintenanceMaterials->value => [0, 250, 0, 250, 0, 250],
            L::Insurance->value => [0, 1000, 90.83, 250, 82.57, 250],
            L::MembershipFees->value => [238.74, 238.74, 243.51, 243.51, 243.51, 250],
            L::FirstAid->value => [0, 100, 63.58, 100, 0, 100],
            L::Administration->value => [0, 0, 57.91, 150, 22.50, 150],
            L::ActivitiesCost->value => [0, 500, 0, 250, 0, 250],
            L::DrinksCost->value => [4344.61, 10000, 5139.73, 5000, 1670.23, 5000],
            L::MusicLight->value => [1066.73, 2000, 2312.53, 3000, 1122.15, 3000],
            L::Pr->value => [175.99, 500, 295.99, 500, 9.99, 250],
            L::VolunteerFees->value => [0, 100, 0, 100, 0, 100],
            L::EventsCost->value => [0, 12500, 1973.90, 4500, 0, 4500],
            L::ActivitiesIncome->value => [0, 750, 0, 500, 0, 300],
            L::DrinksIncome->value => [1034.53, 25000, 8217.41, 10000, 2613.40, 10000],
            L::MemberCards->value => [0, 500, 0, 500, 0, 500],
            L::EventsIncome->value => [0, 12500, 3170, 6000, 0, 6000],
            L::RentalIncome->value => [1418.60, 2000, 1650, 2000, 1600, 2000],
            L::CupsIncome->value => [100.58, 150, 66.25, 150, 15, 150],
            L::Subsidy->value => [3800, 675, 1585.38, 1500, 0, 1500],
        ];

        foreach ($figures as $line => [$actual2024, $budget2025, $actual2025, $budget2026, $actual2026, $budget2027]) {
            foreach ([2024 => $actual2024, 2025 => $actual2025, 2026 => $actual2026] as $year => $amount) {
                $this->entry($year, $line, self::SOURCE.($year === 2026 ? ' (stand 06/11/2026)' : ''), $amount);
            }

            foreach ([2025 => $budget2025, 2026 => $budget2026, 2027 => $budget2027] as $year => $amount) {
                AnnualBudget::query()->updateOrCreate(['year' => $year, 'line' => $line], ['amount' => $amount]);
            }
        }

        $this->entry(2024, L::Investments->value, 'Oprichting VZW', 168.31);
        $this->entry(2025, L::Investments->value, 'Vernieuwing Elektro + Aankoop Elektronische Kassa', 1554.40);
    }

    private function entry(int $year, string $line, string $description, float $amount): void
    {
        if ($amount == 0) {
            return;
        }

        AnnualEntry::query()->create(['year' => $year, 'line' => $line, 'description' => $description, 'amount' => $amount, 'is_import' => true]);
    }
}
