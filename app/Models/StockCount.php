<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A stock count (inventaris). Finalising books the differences as stock movements.
 */
class StockCount extends Model
{
    use HasFactory;

    protected $fillable = ['counted_on', 'note', 'user_id', 'finalized_at'];

    protected function casts(): array
    {
        return ['counted_on' => 'date', 'finalized_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockCountLine::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFinalized(): bool
    {
        return $this->finalized_at !== null;
    }
}
