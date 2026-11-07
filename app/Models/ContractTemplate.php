<?php

namespace App\Models;

use App\Enums\ContractType;
use Illuminate\Database\Eloquent\Model;

/**
 * The editable text of a contract type, with {placeholders} filled in per contract.
 */
class ContractTemplate extends Model
{
    protected $fillable = ['type', 'title', 'body'];

    protected function casts(): array
    {
        return ['type' => ContractType::class];
    }

    public static function for(ContractType $type): self
    {
        return static::query()->where('type', $type)->firstOrFail();
    }
}
