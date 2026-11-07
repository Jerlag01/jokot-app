<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The drink count before and after a rental ("voor en na het evenement tellen we de drank").
 */
class RentalDrinkCount extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'quantity_before', 'quantity_after'];

    protected function casts(): array
    {
        return ['quantity_before' => 'integer', 'quantity_after' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function consumed(): int
    {
        return max(0, $this->quantity_before - $this->quantity_after);
    }
}
