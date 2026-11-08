<?php

namespace App\Http\Middleware;

use App\Support\Seo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * When indexing is switched off (Instellingen → Zoekmachines, or any non-production site), every response
 * also carries "X-Robots-Tag: noindex", so PDFs and images are not indexed either.
 */
class RobotsTag
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $indexable = Seo::indexable();
        } catch (Throwable) {
            $indexable = false;
        }

        if (! $indexable) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
