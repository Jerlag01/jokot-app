<?php

namespace App\Providers;

use App\Support\Security\LoginAttemptListener;
use App\Models\Page;
use App\Support\Settings;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);

        // Mail settings from the admin site apply as soon as the mailer is first used.
        $this->app->afterResolving('mail.manager', fn () => $this->app->make(Settings::class)->applyMailConfig());
    }

    public function boot(): void
    {
        Carbon::setLocale('nl');
        CarbonImmutable::setLocale('nl');

        Event::listen([Failed::class, Login::class], LoginAttemptListener::class);

        View::composer('layouts.site', function ($view): void {
            try {
                $menuPages = Page::query()->published()->where('show_in_menu', true)->orderBy('menu_order')->orderBy('title')->get(['id', 'title', 'slug']);
            } catch (Throwable) {
                $menuPages = collect();
            }

            $view->with('menuPages', $menuPages);
        });
    }
}
