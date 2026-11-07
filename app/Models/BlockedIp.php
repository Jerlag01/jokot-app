<?php

namespace App\Models;

use App\Support\Security\IpBlocker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An IP address or range (CIDR, e.g. 203.0.113.0/24) that gets no access to the site, permanently or until blocked_until.
 */
class BlockedIp extends Model
{
    protected $fillable = ['ip', 'reason', 'is_automatic', 'blocked_until', 'hits', 'last_hit_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'is_automatic' => 'boolean',
            'blocked_until' => 'datetime',
            'last_hit_at' => 'datetime',
            'hits' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => IpBlocker::flush());
        static::deleted(fn () => IpBlocker::flush());
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query->whereNull('blocked_until')->orWhere('blocked_until', '>', now()));
    }

    public function isActive(): bool
    {
        return $this->blocked_until === null || $this->blocked_until->isFuture();
    }
}
