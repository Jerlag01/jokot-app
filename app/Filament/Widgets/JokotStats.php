<?php

namespace App\Filament\Widgets;

use App\Enums\RentalStatus;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\RentalRequests\RentalRequestResource;
use App\Models\Event;
use App\Models\RentalRequest;
use App\Support\Permissions;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class JokotStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasAnyPermission(Permissions::RENTALS, Permissions::EVENTS);
    }

    protected function getStats(): array
    {
        $upcoming = fn () => RentalRequest::query()->whereDate('event_date', '>=', today());

        $user = auth()->user();
        if (! $user->hasPermission(Permissions::RENTALS)) {
            return [
                Stat::make('Komende activiteiten', Event::query()->where('starts_at', '>=', today())->count())
                    ->description('Eigen werking')
                    ->descriptionIcon(Heroicon::OutlinedSparkles)
                    ->color('primary')
                    ->url(EventResource::getUrl('index')),
            ];
        }

        return [
            Stat::make('Nieuwe huuraanvragen', $upcoming()->where('status', RentalStatus::New)->count())
                ->description('Nog te behandelen')
                ->descriptionIcon(Heroicon::OutlinedInboxArrowDown)
                ->color('info')
                ->url(RentalRequestResource::getUrl('index', ['tab' => 'new'])),
            Stat::make('In optie', $upcoming()->where('status', RentalStatus::Option)->count())
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->description('Wachten op bevestiging')
                ->color('warning')
                ->url(RentalRequestResource::getUrl('index', ['tab' => 'option'])),
            Stat::make('Bevestigde verhuren', $upcoming()->where('status', RentalStatus::Confirmed)->count())
                ->description('Vanaf vandaag')
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->url(RentalRequestResource::getUrl('index', ['tab' => 'confirmed'])),
            Stat::make('Komende activiteiten', Event::query()->where('starts_at', '>=', today())->count())
                ->description('Eigen werking')
                ->descriptionIcon(Heroicon::OutlinedSparkles)
                ->color('primary')
                ->url(EventResource::getUrl('index')),
        ];
    }
}
