<?php

namespace Database\Seeders;

use App\Enums\ProductCategory;
use App\Models\Product;
use App\Models\ProductServing;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One-time import of the sheet "Factorberekening JH Niel.xlsx": purchase price per package, VAT, servings,
 * current price (column H-J) and the new prices (column O-Q). Existing products are matched by name,
 * the others are created without stock tracking. Safe to run again (servings are matched by label).
 */
class PricingImportSeeder extends Seeder
{
    /**
     * name in the sheet => [existing product name or null, category, package, package price excl., units, VAT %, servings]
     * servings: [label, per package, current price, new price, primary]
     *
     * @return array<string, array{0: ?string, 1: ProductCategory, 2: ?string, 3: float, 4: int, 5: int, 6: list<array{0: string, 1: float, 2: ?float, 3: ?float, 4: bool}>}>
     */
    public static function rows(): array
    {
        $bottle = fn (?float $price, ?float $new, string $label = 'Flesje', float $per = 24): array => [[$label, $per, $price, $new, true]];

        return [
            'Cola' => ['Cola 20cl', ProductCategory::Soft, 'bak van 24 flesjes', 15.41, 24, 6, $bottle(1.5, 1.3)],
            'Cola Zero' => ['Cola Zero 20cl', ProductCategory::Soft, 'bak van 24 flesjes', 15.41, 24, 6, $bottle(1.5, 1.3)],
            'Fanta' => ['Fanta 20cl', ProductCategory::Soft, 'bak van 24 flesjes', 15.75, 24, 6, $bottle(1.5, 1.3)],
            'Ice Tea' => ['Ice Tea 20cl', ProductCategory::Soft, 'bak van 24 flesjes', 16.16, 24, 6, $bottle(1.5, 1.3)],
            'Ice Tea Green' => ['Ice Tea Green 20cl', ProductCategory::Soft, 'bak van 24 flesjes', 16.16, 24, 6, $bottle(null, 1.3)],
            'Ice Tea Peach' => [null, ProductCategory::Soft, 'bak van 24 flesjes', 16.16, 24, 6, $bottle(null, 1.3)],
            'Vosges plat' => ['Plat water 20cl', ProductCategory::Soft, 'bak van 24 flesjes', 7.61, 24, 6, $bottle(1.0, 1.2)],
            'Vosges Bruis' => ['Bruiswater 20cl', ProductCategory::Soft, 'bak van 24 flesjes', 7.61, 24, 6, $bottle(1.0, 1.2)],
            'Pils Fles (Primus)' => ['Pils 25cl', ProductCategory::Beer, 'bak van 24 flesjes', 18.64, 24, 21, $bottle(1.8, 1.5)],
            'Pils Vat 50L (Primus)' => ['Vat 50L', ProductCategory::Beer, null, 136.97, 1, 21, [
                ['25cl', 185, 1.8, 1.5, false],
                ['50cl', 95, 3.5, 3.0, false],
                ['Meter', 18, 18.0, 15.0, false],
            ]],
            'Kriek (Haacht Mystic)' => ['Kriek 33cl', ProductCategory::Beer, 'bak van 24 flesjes', 26.09, 24, 21, $bottle(2.5, 2.5)],
            'Duvel' => ['Duvel', ProductCategory::Beer, 'bak van 24 flesjes', 30.93, 24, 21, $bottle(3.0, 3.2)],
            'La Chouffe' => ['La Chouffe Blond', ProductCategory::Beer, 'bak van 24 flesjes', 28.53, 24, 21, $bottle(3.0, 3.5)],
            'Vedett' => ['Vedett', ProductCategory::Beer, 'bak van 24 flesjes', 24.01, 24, 21, $bottle(3.0, 4.0)],
            'Strongbow Gold Apple' => [null, ProductCategory::Beer, 'bak van 24 flesjes', 0, 24, 21, $bottle(null, 4.0)],
            'Gouden Carolus Tripel' => [null, ProductCategory::Beer, 'bak van 24 flesjes', 0, 24, 21, $bottle(null, 3.0)],
            'Carlsberg 0%' => ['Carlsberg 0%', ProductCategory::Beer, 'bak van 24 flesjes', 0, 24, 6, $bottle(null, 1.5)],
            'Affligem 0,0%' => [null, ProductCategory::Beer, 'pak van 6 flesjes', 7.3132, 6, 6, $bottle(2.5, 3.0, 'Flesje', 6)],
            'Westmalle Tripel' => [null, ProductCategory::Beer, 'bak van 24 flesjes', 0, 24, 21, $bottle(null, 3.2)],
            'Chips Paprika (Lay\'s)' => [null, ProductCategory::Snack, 'doos van 20 zakjes', 9.17, 20, 6, $bottle(1.0, 1.2, 'Zakje', 20)],
            'Chips Zout (Lay\'s)' => [null, ProductCategory::Snack, 'doos van 20 zakjes', 9.17, 20, 6, $bottle(1.0, 1.2, 'Zakje', 20)],
            // The sheet has no price for these two but uses the same package price incl. VAT (9,72).
            'Chips Pickles (Lay\'s)' => [null, ProductCategory::Snack, 'doos van 20 zakjes', 9.17, 20, 6, $bottle(null, 1.2, 'Zakje', 20)],
            'Chips Peper & Zout (Lay\'s)' => [null, ProductCategory::Snack, 'doos van 20 zakjes', 9.17, 20, 6, $bottle(null, 1.2, 'Zakje', 20)],
            'Witte wijn' => [null, ProductCategory::Wine, 'fles 75cl', 0, 1, 21, [['Glas', 4, null, 3.0, true], ['Fles', 1, null, 12.0, false]]],
            'Rosé' => [null, ProductCategory::Wine, 'fles 75cl', 0, 1, 21, [['Glas', 4, null, 3.2, true], ['Fles', 1, null, 13.0, false]]],
        ];
    }

    public function run(): void
    {
        DB::transaction(function (): void {
            $sort = (int) Product::query()->max('sort');

            foreach (self::rows() as $sheetName => [$existing, $category, $package, $packagePrice, $units, $vat, $servings]) {
                $product = Product::query()->whereIn('name', array_filter([$existing, $sheetName]))->orderByRaw('name = ? desc', [$existing ?? $sheetName])->first()
                    ?? Product::query()->create([
                        'name' => $existing ?? $sheetName,
                        'category' => $category,
                        'package' => $package,
                        'units_per_package' => $units,
                        'is_orderable' => false,
                        'track_stock' => false,
                        'is_active' => true,
                        'sort' => ++$sort,
                    ]);

                $product->update([
                    'package_price' => $packagePrice > 0 ? $packagePrice : null,
                    'vat_rate' => $vat,
                    'units_per_package' => $product->units_per_package ?: $units,
                ]);

                foreach ($servings as $index => [$label, $per, $price, $new, $primary]) {
                    // The migration created a "Stuk" serving for every product with a bar price: that one becomes the primary serving.
                    $serving = $product->servings()->where('label', $label)->first()
                        ?? ($primary ? $product->servings()->where('is_primary', true)->first() : null)
                        ?? new ProductServing(['product_id' => $product->id]);

                    $serving->fill([
                        'label' => $label,
                        'servings_per_package' => $per,
                        // The sheet has 0 where no price was filled in: keep the current bar price then.
                        'price' => $price ?? ($primary ? $product->bar_price : null),
                        'proposed_price' => $new,
                        'is_primary' => $primary,
                        'sort' => $index,
                    ])->save();
                }
            }
        });
    }
}
