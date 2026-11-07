<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A device key for the mobile (check-in) app. Only the SHA-256 hash is stored.
 */
class ApiToken extends Model
{
    protected $fillable = ['name', 'token_hash', 'last_used_at', 'expires_at'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array{0: ApiToken, 1: string} the stored token and the plain value to hand out once
     */
    public static function issue(User $user, string $name): array
    {
        $plain = Str::random(64);

        $token = $user->apiTokens()->create([
            'name' => $name,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addYear(),
        ]);

        return [$token, $plain];
    }

    public static function findValid(string $plain): ?self
    {
        return static::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $plain))
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();
    }
}
