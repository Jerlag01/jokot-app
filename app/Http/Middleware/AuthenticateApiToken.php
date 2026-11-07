<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Authorization: Bearer <token>" for the mobile app. Inactive users and expired tokens are refused.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $plain = $request->bearerToken();
        $token = $plain ? ApiToken::findValid($plain) : null;

        if (! $token || ! $token->user?->is_active) {
            return response()->json(['message' => 'Niet aangemeld of sessie verlopen.'], 401);
        }

        if ($permission && ! $token->user->hasPermission($permission)) {
            return response()->json(['message' => 'Geen toegang.'], 403);
        }

        if (! $token->last_used_at || $token->last_used_at->lt(now()->subMinutes(5))) {
            $token->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        Auth::setUser($token->user);
        $request->attributes->set('api_token', $token);

        return $next($request);
    }
}
