<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Who sends us invoices: brewery, drinks wholesaler, energy, artists, ...
 */
class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'vat_number', 'email', 'phone', 'iban', 'bic', 'street', 'house_number', 'postal_code', 'city', 'country',
        'default_category_id', 'payment_days', 'notes',
    ];

    protected function casts(): array
    {
        return ['payment_days' => 'integer'];
    }

    public function defaultCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'default_category_id');
    }

    public function purchaseInvoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class);
    }
}
