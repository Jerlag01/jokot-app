<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\User;
use App\Support\Permissions;
use App\Support\Security\IpBlocker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        $reason = match (true) {
            ! $user => 'Onbekend e-mailadres',
            ! Hash::check($data['password'], $user->password) => 'Verkeerd wachtwoord',
            ! $user->is_active => 'Gebruiker niet actief',
            ! $user->hasPermission(Permissions::CHECKIN) => 'Geen check-in-recht',
            default => null,
        };

        app(IpBlocker::class)->record($data['email'], $request->ip(), $reason === null, 'api', $user, $reason, $request->userAgent());

        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->is_active) {
            return response()->json(['message' => 'E-mail of wachtwoord klopt niet.'], 422);
        }

        if (! $user->hasPermission(Permissions::CHECKIN)) {
            return response()->json(['message' => 'Deze gebruiker mag geen tickets inchecken.'], 403);
        }

        [$token, $plain] = ApiToken::issue($user, $data['device_name']);

        return response()->json([
            'token' => $plain,
            'expires_at' => $token->expires_at->toIso8601String(),
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->attributes->get('api_token')?->delete();

        return response()->json(['message' => 'Afgemeld.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['id' => $user->id, 'name' => $user->name, 'email' => $user->email]);
    }
}
