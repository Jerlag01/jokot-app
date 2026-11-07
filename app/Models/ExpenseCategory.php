<?php

namespace App\Models;

use App\Enums\AnnualLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A cost category for incoming invoices (drinks, energy, artists, ...), for the cost overview.
 */
class ExpenseCategory extends Model
{
    protected $fillable = ['name', 'annual_line', 'sort', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'annual_line' => AnnualLine::class];
    }

    public function purchaseInvoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort')->orderBy('name');
    }
}
