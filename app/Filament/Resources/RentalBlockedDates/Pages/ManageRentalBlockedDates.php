<?php

namespace App\Filament\Resources\RentalBlockedDates\Pages;

use App\Filament\Resources\RentalBlockedDates\RentalBlockedDateResource;
use App\Support\Availability;
use App\Support\RentalWeekdays;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageRentalBlockedDates extends ManageRecords
{
    protected static string $resource = RentalBlockedDateResource::class;

    public function getSubheading(): ?string
    {
        $weekdays = app(Availability::class)->blockedWeekdays();

        return $weekdays === []
            ? 'Op deze dagen kan niemand de zaal aanvragen via de website. Het bestuur kan zelf wel nog een verhuur invoeren.'
            : 'Nooit aanvragen op: '.RentalWeekdays::describe($weekdays).'. Daarnaast ook niet op de data hieronder.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('weekdays')
                ->label('Weekdagen zonder aanvragen')
                ->icon(Heroicon::OutlinedCalendar)
                ->color('gray')
                ->modalDescription('Op deze dagen van de week kan de zaal nooit aangevraagd worden via de website.')
                ->fillForm(fn (): array => ['weekdays' => array_map('strval', app(Availability::class)->blockedWeekdays())])
                ->schema([CheckboxList::make('weekdays')->hiddenLabel()->options(RentalWeekdays::options())->columns(2)])
                ->action(function (array $data): void {
                    RentalWeekdays::save($data['weekdays'] ?? []);
                    Notification::make()->success()->title('Weekdagen opgeslagen')->send();
                }),
            CreateAction::make()->label('Datum blokkeren'),
        ];
    }
}
