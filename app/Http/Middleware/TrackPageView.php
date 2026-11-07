<?php

namespace App\Http\Middleware;

use App\Support\Stats\VisitorTracker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Counts the page view after the response was sent, so visitors never wait for it.
 */
class TrackPageView
{
    public function __construct(private VisitorTracker $tracker) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            if ($this->tracker->shouldTrack($request, $response)) {
                $this->tracker->track($request);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
