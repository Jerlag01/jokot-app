<x-filament-panels::page>
    @php
        $pricing = $this->pricing();
        $summary = $pricing->summary();
        $target = $pricing->targetFactor();
    @endphp

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)); gap: 1rem;">
        <x-filament::section>
            <div style="font-size: .8rem; opacity: .7;">Gemiddelde factor nu</div>
            <div style="font-size: 1.6rem; font-weight: 700; color: {{ $summary['current'] !== null && $summary['current'] < $target ? '#e9204f' : 'inherit' }};">{{ static::factorLabel($summary['current']) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div style="font-size: .8rem; opacity: .7;">Gemiddelde factor met nieuwe prijzen</div>
            <div style="font-size: 1.6rem; font-weight: 700;">{{ static::factorLabel($summary['proposed']) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div style="font-size: .8rem; opacity: .7;">Doelfactor</div>
            <div style="font-size: 1.6rem; font-weight: 700;">{{ static::factorLabel($target) }}</div>
            <div style="font-size: .75rem; opacity: .7;">afgerond op {{ static::money($pricing->rounding()) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div style="font-size: .8rem; opacity: .7;">Onder de doelfactor</div>
            <div style="font-size: 1.6rem; font-weight: 700; color: {{ $summary['below_target'] ? '#e9204f' : 'inherit' }};">{{ $summary['below_target'] }}</div>
            @if ($summary['missing_cost'])
                <div style="font-size: .75rem; opacity: .7;">{{ $summary['missing_cost'] }} zonder aankoopprijs</div>
            @endif
        </x-filament::section>
    </div>

    <p style="font-size: .85rem; opacity: .75; margin: 0;">
        Factor = aantal porties per verpakking × verkoopprijs ÷ aankoopprijs van de verpakking incl. btw.
        Pas de verkoopprijs of de nieuwe prijs rechtstreeks in de tabel aan; met "Nieuwe prijzen toepassen" worden ze de echte prijzen.
    </p>

    {{ $this->table }}
</x-filament-panels::page>
