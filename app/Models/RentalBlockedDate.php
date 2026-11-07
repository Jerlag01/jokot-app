<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A day or period on which the website accepts no rental requests (works, holidays, own plans...).
 */
class RentalBlockedDate extends Model
{
    protected $fillable = ['starts_on', 'ends_on', 'reason'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    protected static function booted(): void
    {
        static::saving(function (RentalBlockedDate $blocked): void {
            $blocked->ends_on ??= $blocked->starts_on;

            if ($blocked->ends_on->lessThan($blocked->starts_on)) {
                [$blocked->starts_on, $blocked->ends_on] = [$blocked->ends_on, $blocked->starts_on];
            }
        });
    }

    public function scopeOverlapping(Builder $query, string $from, string $to): void
    {
        $query->where('starts_on', '<=', $to)->where('ends_on', '>=', $from);
    }

    public function days(): int
    {
        return (int) $this->starts_on->diffInDays($this->ends_on) + 1;
    }
}
