<?php

namespace App\Models;

use App\Enums\AnnualLine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A manual booking in the yearly accounts: subsidy, volunteer fees, cash expenses without invoice,
 * or figures taken over from the old Excel workbook (is_import).
 */
class AnnualEntry extends Model
{
    use HasFactory;

    protected $fillable = ['year', 'line', 'date', 'description', 'amount', 'is_import', 'user_id'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'line' => AnnualLine::class, 'date' => 'date', 'amount' => 'decimal:2', 'is_import' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
