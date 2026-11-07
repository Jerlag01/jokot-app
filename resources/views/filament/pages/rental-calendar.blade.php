<x-filament-panels::page>
    @php($calendar = $this->calendar())
    <x-filament::section>
        <div class="jk-cal">
            @foreach (['Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo'] as $dayName)
                <div class="jk-cal-head">{{ $dayName }}</div>
            @endforeach
            @for ($day = $calendar['start']; $day->lessThanOrEqualTo($calendar['end']); $day = $day->addDay())
                <div @class(['jk-cal-day', 'is-outside' => ! $day->isSameMonth($calendar['month']), 'is-today' => $day->isToday()])>
                    <span class="jk-cal-num">{{ $day->day }}</span>
                    @foreach ($calendar['items'][$day->toDateString()] ?? [] as $item)
                        <a href="{{ $item['url'] }}" class="jk-cal-item {{ $item['class'] }}" title="{{ $item['label'] }}">{{ $item['label'] }}</a>
                    @endforeach
                </div>
            @endfor
        </div>
        <div style="display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 1rem; font-size: .8rem;">
            <span><span class="jk-cal-item is-new" style="display: inline-block;">Nieuw</span></span>
            <span><span class="jk-cal-item is-option" style="display: inline-block;">In optie</span></span>
            <span><span class="jk-cal-item is-confirmed" style="display: inline-block;">Bevestigd</span></span>
            <span><span class="jk-cal-item is-event" style="display: inline-block;">Eigen activiteit</span></span>
            <span><span class="jk-cal-item is-blocked" style="display: inline-block;">Geen aanvragen via de website</span></span>
        </div>
    </x-filament::section>
</x-filament-panels::page>
