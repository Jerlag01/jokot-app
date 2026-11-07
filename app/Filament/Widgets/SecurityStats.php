<?php

namespace App\Filament\Widgets;

use App\Models\BlockedIp;
use App\Models\LoginAttempt;
use App\Support\Security\GeoIp;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Failed sign-ins, where they come from and how many addresses are blocked. Shown above the login log only.
 */
class SecurityStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $failed = fn ($since) => LoginAttempt::query()->where('successful', false)->where('created_at', '>=', $since);

        $countries = $failed(now()->subDays(30))
            ->selectRaw('country_code, COUNT(*) AS total')
            ->groupBy('country_code')
            ->orderByDesc('total')
            ->limit(3)
            ->get()
            ->map(fn ($row): string => GeoIp::label($row->country_code).' ('.$row->total.')')
            ->implode(', ');

        $logins = $failed(now()->subDays(30))
            ->whereNotNull('email')
            ->selectRaw('email, COUNT(*) AS total')
            ->groupBy('email')
            ->orderByDesc('total')
            ->limit(3)
            ->get()
            ->map(fn ($row): string => $row->email.' ('.$row->total.')')
            ->implode(', ');

        return [
            Stat::make('Mislukt (24 uur)', $failed(now()->subDay())->count())->color('danger'),
            Stat::make('Mislukt (30 dagen)', $failed(now()->subDays(30))->count())
                ->description($failed(now()->subDays(30))->distinct()->count('ip').' verschillende IP-adressen'),
            Stat::make('Meeste pogingen uit', $countries !== '' ? explode(' (', $countries)[0] : '-')->description($countries ?: 'Geen mislukte aanmeldingen'),
            Stat::make('Meest geprobeerde login', $logins !== '' ? explode(' (', $logins)[0] : '-')->description($logins ?: 'Geen mislukte aanmeldingen'),
            Stat::make('Geblokkeerde IP\'s', BlockedIp::query()->active()->count())->description(BlockedIp::query()->active()->where('is_automatic', true)->count().' automatisch'),
        ];
    }
}
