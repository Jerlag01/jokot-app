{{--
    The month grid of the availability calendar. Rendered inside the calendar block, and on its own by
    CalendarController so the arrows can swap months without reloading the page.

    @var \Carbon\CarbonImmutable|null $month
    @var bool $withForm  whether the rental form is on the same page (then a free day fills it in)
--}}
@php
    $availability = app(\App\Support\Availability::class);
    [$earliest, $latest] = $availability->bookingWindow();
    $firstMonth = \Carbon\CarbonImmutable::today()->startOfMonth();
    $lastMonth = $latest->startOfMonth();
    $month = $month ?? $firstMonth;
    $month = $month->lessThan($firstMonth) ? $firstMonth : ($month->greaterThan($lastMonth) ? $lastMonth : $month);
    $gridStart = $month->startOfWeek(\Carbon\CarbonInterface::MONDAY);
    $gridEnd = $month->endOfMonth()->endOfWeek(\Carbon\CarbonInterface::SUNDAY);
    $taken = $availability->takenBetween($gridStart, $gridEnd);
    $blocked = $availability->blockedBetween($gridStart, $gridEnd);
@endphp
<div class="calendar-head">
    @if ($month->greaterThan($firstMonth))
        <a class="calendar-nav" href="?maand={{ $month->subMonth()->format('Y-m') }}#kalender" data-calendar-month="{{ $month->subMonth()->format('Y-m') }}" aria-label="Vorige maand">&lsaquo;</a>
    @else
        <span class="calendar-nav is-disabled">&lsaquo;</span>
    @endif
    <strong aria-live="polite">{{ ucfirst($month->translatedFormat('F Y')) }}</strong>
    @if ($month->lessThan($lastMonth))
        <a class="calendar-nav" href="?maand={{ $month->addMonth()->format('Y-m') }}#kalender" data-calendar-month="{{ $month->addMonth()->format('Y-m') }}" aria-label="Volgende maand">&rsaquo;</a>
    @else
        <span class="calendar-nav is-disabled">&rsaquo;</span>
    @endif
</div>
<div class="calendar-grid">
    @foreach (['ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'] as $dayName)
        <span class="calendar-dayname">{{ $dayName }}</span>
    @endforeach
    @for ($day = $gridStart; $day->lessThanOrEqualTo($gridEnd); $day = $day->addDay())
        @php
            $key = $day->toDateString();
            $state = match (true) {
                ! $day->isSameMonth($month) => 'outside',
                isset($taken[$key]) => $taken[$key] === \App\Support\Availability::OWN_EVENT ? 'event' : 'taken',
                $day->lessThan($earliest) || $day->greaterThan($latest) || isset($blocked[$key]) => 'closed',
                default => 'free',
            };
        @endphp
        @if ($state === 'free')
            <a class="calendar-day is-free" href="{{ $withForm ? '#huuraanvraag' : url('/verhuren').'?datum='.$key.'#huuraanvraag' }}" data-pick-date="{{ $key }}" title="Vrij - klik om deze datum aan te vragen">{{ $day->day }}</a>
        @else
            <span class="calendar-day is-{{ $state }}" @if ($state === 'taken') title="Bezet" @elseif ($state === 'event') title="Eigen activiteit" @elseif ($state === 'closed') title="Niet reserveerbaar" @endif>{{ $state === 'outside' ? '' : $day->day }}</span>
        @endif
    @endfor
</div>
