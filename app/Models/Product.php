<?php

namespace App\Models;

use App\Enums\ProductCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A drink or snack: bar price, price for renters, stock. Orderable products appear in the rental request form.
 * Stock is counted in units (bottles, kegs, bags); units_per_package converts crates.
 */
class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'category', 'package', 'units_per_package', 'package_price', 'vat_rate', 'bar_price', 'rental_price', 'purchase_price',
        'is_orderable', 'track_stock', 'stock_quantity', 'min_stock', 'sort', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'category' => ProductCategory::class,
            'bar_price' => 'decimal:2',
            'rental_price' => 'decimal:2',
            'purchase_price' => 'decimal:2',
            'package_price' => 'decimal:4',
            'vat_rate' => 'decimal:2',
            'is_orderable' => 'boolean',
            'track_stock' => 'boolean',
            'is_active' => 'boolean',
            'stock_quantity' => 'integer',
            'min_stock' => 'integer',
            'units_per_package' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // The bar price and the primary serving of the price calculation are the same price.
        static::saved(function (Product $product): void {
            if ($product->wasChanged('bar_price')) {
                $product->servings()->where('is_primary', true)->get()->each->updateQuietly(['price' => $product->bar_price]);
            }
        });
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function servings(): HasMany
    {
        return $this->hasMany(ProductServing::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * Purchase price of one package (crate, keg, bottle of wine) including VAT, or null when unknown.
     */
    public function packagePriceInclVat(): ?float
    {
        if ($this->package_price === null || (float) $this->package_price <= 0) {
            return null;
        }

        return (float) $this->package_price * (1 + (float) $this->vat_rate / 100);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort')->orderBy('id');
    }

    public function scopeOrderable(Builder $query): void
    {
        $query->active()->where('is_orderable', true);
    }

    public function scopeTracked(Builder $query): void
    {
        $query->where('is_active', true)->where('track_stock', true);
    }

    public function scopeLowStock(Builder $query): void
    {
        $query->tracked()->whereNotNull('min_stock')->whereColumn('stock_quantity', '<=', 'min_stock');
    }

    public function label(): string
    {
        return $this->package ? "{$this->name} ({$this->package})" : $this->name;
    }

    public function isLow(): bool
    {
        return $this->track_stock && $this->min_stock !== null && $this->stock_quantity <= $this->min_stock;
    }

    /**
     * "50 (2 bakken + 2)" for products sold per crate.
     */
    public function stockLabel(?int $quantity = null): string
    {
        $quantity ??= $this->stock_quantity;

        if (! $this->units_per_package || $this->units_per_package < 2 || abs($quantity) < $this->units_per_package) {
            return (string) $quantity;
        }

        $packages = intdiv($quantity, $this->units_per_package);
        $rest = $quantity % $this->units_per_package;

        return "{$quantity} ({$packages} ".($packages === 1 ? 'bak' : 'bakken').($rest ? " + {$rest}" : '').')';
    }
}
