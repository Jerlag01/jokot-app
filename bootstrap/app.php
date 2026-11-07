<?php

use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\BlockIps;
use App\Http\Middleware\TrackPageView;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Payment providers post here without a CSRF token; the payload is verified (Stripe) or re-fetched (SumUp).
        $middleware->preventRequestForgery(except: ['webhooks/*']);
        $middleware->alias(['api.token' => AuthenticateApiToken::class]);
        // Blocked IP addresses get nothing, not even the login page; public page views are counted.
        $middleware->prepend(BlockIps::class);
        $middleware->web(append: [TrackPageView::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
