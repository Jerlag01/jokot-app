<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One sign-in on the admin site or the check-in app, successful or not, with the e-mail that was tried.
 */
class LoginAttempt extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['email', 'user_id', 'ip', 'country_code', 'channel', 'successful', 'reason', 'user_agent'];

    protected function casts(): array
    {
        return ['successful' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
