<?php

namespace App\Models;

use App\Enums\InsuranceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * An insurance policy with its documents (on the private disk).
 */
class Insurance extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', 'name', 'insurer', 'broker', 'policy_number', 'starts_on', 'renewal_date', 'ends_on', 'premium', 'premium_frequency',
        'coverage', 'contact', 'documents', 'document_names', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => InsuranceType::class,
            'starts_on' => 'date',
            'renewal_date' => 'date',
            'ends_on' => 'date',
            'premium' => 'decimal:2',
            'documents' => 'array',
            'document_names' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (Insurance $insurance): void {
            Storage::disk('local')->delete($insurance->documents ?? []);
        });
    }

    public function scopeRenewingSoon(Builder $query, int $days = 60): void
    {
        $query->where('is_active', true)->whereNotNull('renewal_date')->whereBetween('renewal_date', [today(), today()->addDays($days)]);
    }

    public function documentName(string $path): string
    {
        return $this->document_names[$path] ?? basename($path);
    }
}
