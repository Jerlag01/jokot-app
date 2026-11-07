<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Enums\RentalStatus;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\RentalBlockedDates\RentalBlockedDateResource;
use App\Filament\Resources\RentalRequests\RentalRequestResource;
use App\Models\Event;
use App\Models\RentalRequest;
use App\Support\Permissions;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Month view of rentals (new, in option, confirmed) and own activities.
 */
class RentalCalendar extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Rentals;

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasPermission(Permissions::RENTALS);
    }

    protected static ?string $navigationLabel = 'Kalender';

    protected static ?string $slug = 'calendar';

    protected string $view = 'filament.pages.rental-calendar';

    #[Url]
    public ?string $month = null;

    public function getTitle(): string
    {
        return 'Kalender - '.ucfirst($this->currentMonth()->translatedFormat('F Y'));
    }

    public function currentMonth(): CarbonImmutable
    {
        if ($this->month && preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            return CarbonImmutable::createFromFormat('!Y-m', $this->month);
        }

        return CarbonImmutable::today()->startOfMonth();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previous')->label('Vorige')->icon(Heroicon::ChevronLeft)->color('gray')
                ->action(fn () => $this->month = $this->currentMonth()->subMonth()->format('Y-m')),
            Action::make('today')->label('Vandaag')->color('gray')
                ->action(fn () => $this->month = null),
            Action::make('next')->label('Volgende')->icon(Heroicon::ChevronRight)->iconPosition('after')->color('gray')
                ->action(fn () => $this->month = $this->currentMonth()->addMonth()->format('Y-m')),
        ];
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable, month: CarbonImmutable, items: array<string, list<array{label: string, class: string, url: string}>>}
     */
    public function calendar(): array
    {
        $month = $this->currentMonth();
        $start = $month->startOfWeek(CarbonInterface::MONDAY);
        $end = $month->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY);
        $items = [];

        RentalRequest::query()
            ->whereIn('status', [RentalStatus::New, RentalStatus::Option, RentalStatus::Confirmed])
            ->whereBetween('event_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('created_at')
            ->get()
            ->each(function (RentalRequest $rental) use (&$items): void {
                $items[$rental->event_date->toDateString()][] = [
                    'label' => $rental->status->getLabel().': '.($rental->organisation ?: $rental->fullName()),
                    'class' => 'is-'.$rental->status->value,
                    'url' => RentalRequestResource::getUrl('view', ['record' => $rental]),
                ];
            });

        Event::query()
            ->whereBetween('starts_at', [$start->startOfDay(), $end->endOfDay()])
            ->orderBy('starts_at')
            ->get()
            ->each(function (Event $event) use (&$items): void {
                $items[$event->starts_at->toDateString()][] = [
                    'label' => $event->starts_at->format('H:i').' '.$event->title,
                    'class' => 'is-event',
                    'url' => EventResource::getUrl('edit', ['record' => $event]),
                ];
            });

        $blocked = app(\App\Support\Availability::class)->blockedBetween($start, $end);

        foreach ($blocked as $date => $reason) {
            $items[$date][] = ['label' => $reason, 'class' => 'is-blocked', 'url' => RentalBlockedDateResource::getUrl()];
        }

        return ['start' => $start, 'end' => $end, 'month' => $month, 'items' => $items, 'blocked' => $blocked];
    }
}
