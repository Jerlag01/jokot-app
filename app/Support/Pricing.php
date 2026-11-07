<?php

namespace App\Support;

use App\Enums\ProductCategory;
use App\Models\ProductServing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Factorberekening, as in the board's sheet "Factorberekening JH Niel":
 *   factor = servings per package × selling price / package price incl. VAT
 * The recommended price is the cost per serving incl. VAT × the target factor, rounded up.
 */
class Pricing
{
    public function targetFactor(?ProductCategory $category = null): float
    {
        $override = $category ? settings()->get('pricing.target_'.$category->value) : null;

        return (float) (filled($override) && (float) $override > 0 ? $override : (settings()->get('pricing.target_factor') ?: 2));
    }

    public function rounding(): float
    {
        $step = (float) settings()->get('pricing.rounding');

        return $step > 0 ? $step : 0.1;
    }

    public function costPerServing(ProductServing $serving): ?float
    {
        $package = $serving->product?->packagePriceInclVat();

        if ($package === null || (float) $serving->servings_per_package <= 0) {
            return null;
        }

        return $package / (float) $serving->servings_per_package;
    }

    /**
     * Null when the purchase price or the selling price is missing (those rows are left out of the average).
     */
    public function factor(ProductServing $serving, mixed $price): ?float
    {
        $cost = $this->costPerServing($serving);

        if ($cost === null || $price === null || (float) $price <= 0) {
            return null;
        }

        return (float) $price / $cost;
    }

    public function recommendedPrice(ProductServing $serving): ?float
    {
        $cost = $this->costPerServing($serving);

        if ($cost === null) {
            return null;
        }

        $step = $this->rounding();

        // round() first so 1.4000000001 / 0.1 does not round up to 1.50.
        return round(ceil(round($cost * $this->targetFactor($serving->product->category) / $step, 6)) * $step, 2);
    }

    public static function query(): Builder
    {
        return ProductServing::query()
            ->select('product_servings.*')
            ->join('products', 'products.id', '=', 'product_servings.product_id')
            ->where('products.is_active', true)
            ->with('product');
    }

    /**
     * @return Collection<int, ProductServing>
     */
    public function servings(): Collection
    {
        return self::query()->orderBy('products.sort')->orderBy('products.id')->orderBy('product_servings.sort')->orderBy('product_servings.id')->get();
    }

    /**
     * Average factor over all servings that have both a purchase price and a price (like cell A2 of the sheet).
     *
     * @return array{current: ?float, proposed: ?float, below_target: int, missing_cost: int}
     */
    public function summary(): array
    {
        $servings = $this->servings();
        $current = $servings->map(fn (ProductServing $serving): ?float => $this->factor($serving, $serving->price))->filter(fn (?float $factor): bool => $factor !== null);
        $proposed = $servings->map(fn (ProductServing $serving): ?float => $this->factor($serving, $serving->proposed_price ?? $serving->price))->filter(fn (?float $factor): bool => $factor !== null);

        return [
            'current' => $current->isEmpty() ? null : $current->avg(),
            'proposed' => $proposed->isEmpty() ? null : $proposed->avg(),
            'below_target' => $servings->filter(function (ProductServing $serving): bool {
                $factor = $this->factor($serving, $serving->price);

                return $factor !== null && $factor < $this->targetFactor($serving->product->category) - 0.005;
            })->count(),
            'missing_cost' => $servings->filter(fn (ProductServing $serving): bool => $this->costPerServing($serving) === null)->count(),
        ];
    }

    /**
     * Fills the "new price" column with the recommended price. Returns how many servings changed.
     *
     * @param  iterable<ProductServing>|null  $servings
     */
    public function proposeRecommended(?iterable $servings = null): int
    {
        $changed = 0;

        foreach ($servings ?? $this->servings() as $serving) {
            $recommended = $this->recommendedPrice($serving);

            if ($recommended !== null && (float) $serving->proposed_price !== $recommended) {
                $serving->update(['proposed_price' => $recommended]);
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * Makes the new prices the current ones (the primary serving updates the bar price) and clears the column.
     */
    public function applyProposed(): int
    {
        return DB::transaction(function (): int {
            $servings = $this->servings()->whereNotNull('proposed_price');

            foreach ($servings as $serving) {
                $serving->update(['price' => $serving->proposed_price, 'proposed_price' => null]);
            }

            return $servings->count();
        });
    }
}
