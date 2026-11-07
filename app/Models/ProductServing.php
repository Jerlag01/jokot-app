<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One way a product is sold: a bottle from a crate, a 25cl/50cl/meter from a keg, a glass of wine.
 * servings_per_package says how many of these come out of one purchased package (for a 50L keg: 185 × 25cl).
 * The primary serving's price is the product's bar price.
 */
class ProductServing extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'label', 'servings_per_package', 'price', 'proposed_price', 'is_primary', 'sort'];

    protected function casts(): array
    {
        return [
            'servings_per_package' => 'decimal:2',
            'price' => 'decimal:2',
            'proposed_price' => 'decimal:2',
            'is_primary' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (ProductServing $serving): void {
            if (! $serving->is_primary || ! ($serving->wasRecentlyCreated || $serving->wasChanged(['price', 'is_primary']))) {
                return;
            }

            static::query()->where('product_id', $serving->product_id)->whereKeyNot($serving->getKey())->where('is_primary', true)->update(['is_primary' => false]);
            $serving->product?->updateQuietly(['bar_price' => $serving->price]);
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
