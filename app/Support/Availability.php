<?php

namespace App\Support;

use App\Models\Event;
use App\Models\RentalBlockedDate;
use App\Models\RentalRequest;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

/**
 * Which dates are taken: by the youth centre's own events ("eigen werking" has priority) or by rentals in option/confirmed.
 * Separately, blocked dates and weekdays: no requests via the website then (the board can still enter a rental itself).
 */
class Availability
{
    public const OWN_EVENT = 'event';

    public const RENTAL = 'rental';

    /**
     * @return array<string, string> date (Y-m-d) => self::OWN_EVENT|self::RENTAL
     */
    public function takenBetween(CarbonInterface $from, CarbonInterface $to, ?int $ignoreRentalId = null): array
    {
        $taken = [];

        RentalRequest::query()
            ->blocking()
            ->whereBetween('event_date', [$from->toDateString(), $to->toDateString()])
            ->when($ignoreRentalId, fn ($query) => $query->whereKeyNot($ignoreRentalId))
            ->pluck('event_date')
            ->each(function (CarbonInterface $date) use (&$taken): void {
                $taken[$date->toDateString()] = self::RENTAL;
            });

        Event::query()
            ->where('blocks_rentals', true)
            ->where('starts_at', '<=', $to->copy()->endOfDay())
            ->where(fn ($query) => $query
                ->where('starts_at', '>=', $from->copy()->startOfDay())
                ->orWhere('ends_at', '>=', $from->copy()->startOfDay()))
            ->get(['starts_at', 'ends_at'])
            ->each(function (Event $event) use (&$taken): void {
                foreach ($this->eventDates($event) as $date) {
                    $taken[$date] = self::OWN_EVENT;
                }
            });

        return $taken;
    }

    public function isAvailable(CarbonInterface $date, ?int $ignoreRentalId = null): bool
    {
        return ! array_key_exists($date->toDateString(), $this->takenBetween($date, $date, $ignoreRentalId));
    }

    /**
     * Free and not blocked: the website accepts a request for this date.
     */
    public function isRequestable(CarbonInterface $date): bool
    {
        return $this->isAvailable($date) && $this->blockedBetween($date, $date) === [];
    }

    /**
     * @return list<int> ISO weekdays (1 = monday ... 7 = sunday) without requests
     */
    public function blockedWeekdays(): array
    {
        return array_values(array_unique(array_map('intval', array_filter((array) settings()->get('rental_blocked_weekdays', []), 'is_numeric'))));
    }

    /**
     * Blocked dates in the range with the reason shown to the board.
     *
     * @return array<string, string> date (Y-m-d) => reason
     */
    public function blockedBetween(CarbonInterface $from, CarbonInterface $to): array
    {
        $blocked = [];
        $weekdays = $this->blockedWeekdays();

        if ($weekdays !== []) {
            foreach (CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()) as $date) {
                if (in_array($date->dayOfWeekIso, $weekdays, true)) {
                    $blocked[$date->toDateString()] = 'Geen aanvragen op '.$date->translatedFormat('l');
                }
            }
        }

        RentalBlockedDate::query()
            ->overlapping($from->toDateString(), $to->toDateString())
            ->get()
            ->each(function (RentalBlockedDate $period) use (&$blocked, $from, $to): void {
                $start = $period->starts_on->max($from->copy()->startOfDay());
                $end = $period->ends_on->min($to->copy()->startOfDay());

                foreach (CarbonPeriod::create($start, $end) as $date) {
                    $blocked[$date->toDateString()] = $period->reason ?: 'Geblokkeerd';
                }
            });

        ksort($blocked);

        return $blocked;
    }

    /**
     * The first and last date the public form accepts (minimum one month, maximum six months ahead by default).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function bookingWindow(): array
    {
        $today = CarbonImmutable::today();

        return [
            $today->addDays((int) settings()->get('rental_min_days_ahead', 30)),
            $today->addMonthsNoOverflow((int) settings()->get('rental_max_months_ahead', 6)),
        ];
    }

    /**
     * An event that ends before 06:00 the next morning only occupies its start date.
     *
     * @return list<string>
     */
    private function eventDates(Event $event): array
    {
        $start = $event->starts_at->copy()->startOfDay();
        $end = $event->ends_at?->copy()->subHours(6)->startOfDay();

        if ($end === null || $end->lessThanOrEqualTo($start)) {
            return [$start->toDateString()];
        }

        return collect(CarbonPeriod::create($start, $end))->map(fn (CarbonInterface $date): string => $date->toDateString())->all();
    }
}
