<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A rental price per user group (youth, associations, private/company).
 */
class RentalRate extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'price', 'deposit', 'sort', 'is_active'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'deposit' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort')->orderBy('id');
    }
}
