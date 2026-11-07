<?php

namespace App\Http\Middleware;

use App\Support\Security\IpBlocker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Refuses every request from a blocked IP address (Beveiliging → Geblokkeerde IP's).
 */
class BlockIps
{
    public function __construct(private IpBlocker $blocker) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $block = $this->blocker->blockFor($request->ip());
        } catch (Throwable) {
            $block = null; // never lock everyone out because the database is unreachable
        }

        if ($block !== null) {
            $this->blocker->registerHit($block);

            return response('Toegang geweigerd.', 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        return $next($request);
    }
}
