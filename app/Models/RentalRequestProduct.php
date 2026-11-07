<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A drink line the renter ordered in advance. The name is copied so the request stays readable if the product is removed.
 */
class RentalRequestProduct extends Model
{
    protected $fillable = ['product_id', 'name', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function rentalRequest(): BelongsTo
    {
        return $this->belongsTo(RentalRequest::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
