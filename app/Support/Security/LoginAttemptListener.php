<?php

namespace App\Support\Security;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;

/**
 * Logs sign-ins on the admin site (the check-in app logs its own in the API controller).
 */
class LoginAttemptListener
{
    public function __construct(private IpBlocker $blocker) {}

    public function handle(Failed|Login $event): void
    {
        $request = request();
        $user = $event->user instanceof User ? $event->user : null;

        if ($event instanceof Failed) {
            $this->blocker->record(
                $event->credentials['email'] ?? null,
                $request->ip(),
                false,
                'admin',
                $user,
                $user ? 'Verkeerd wachtwoord' : 'Onbekend e-mailadres',
                $request->userAgent(),
            );

            return;
        }

        $active = $user === null || $user->is_active;

        $this->blocker->record($user?->email, $request->ip(), $active, 'admin', $user, $active ? null : 'Gebruiker niet actief', $request->userAgent());
    }
}
