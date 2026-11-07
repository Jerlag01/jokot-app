<?php

namespace App\Models;

use App\Enums\AnnualLine;
use Illuminate\Database\Eloquent\Model;

class AnnualBudget extends Model
{
    protected $fillable = ['year', 'line', 'amount'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'line' => AnnualLine::class, 'amount' => 'decimal:2'];
    }
}
