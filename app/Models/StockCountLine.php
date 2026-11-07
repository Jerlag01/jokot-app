<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'expected', 'counted'];

    protected function casts(): array
    {
        return ['expected' => 'integer', 'counted' => 'integer'];
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function difference(): ?int
    {
        return $this->counted === null ? null : $this->counted - $this->expected;
    }
}
