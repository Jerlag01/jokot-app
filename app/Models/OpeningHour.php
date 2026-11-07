<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpeningHour extends Model
{
    public const DAYS = [1 => 'Maandag', 2 => 'Dinsdag', 3 => 'Woensdag', 4 => 'Donderdag', 5 => 'Vrijdag', 6 => 'Zaterdag', 7 => 'Zondag'];

    protected $fillable = ['weekday', 'is_closed', 'opens_at', 'closes_at', 'note'];

    protected function casts(): array
    {
        return ['is_closed' => 'boolean', 'weekday' => 'integer'];
    }

    public function dayName(): string
    {
        return self::DAYS[$this->weekday] ?? '?';
    }
}
