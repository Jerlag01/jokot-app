<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One change of a product's stock. Created only through App\Support\Stock, which keeps balance_after right.
 */
class StockMovement extends Model
{
    protected $fillable = ['product_id', 'type', 'quantity', 'balance_after', 'note', 'source_type', 'source_id', 'user_id'];

    protected function casts(): array
    {
        return ['type' => StockMovementType::class, 'quantity' => 'integer', 'balance_after' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
