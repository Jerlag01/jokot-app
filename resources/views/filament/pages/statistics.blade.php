<x-filament-panels::page>
    @php
        $stats = $this->stats();
        $period = array_key_exists($this->period, \App\Support\Stats\VisitorStats::PERIODS) ? $this->period : '30d';
        $totals = $stats->totals($period);
        $timeline = $stats->timeline($period);
        $max = max(1, collect($timeline)->max('visitors'));
        $pages = $stats->topPages($period);
        $sources = $stats->sources($period);
        $sourceTotal = max(1, array_sum($sources));
        $engines = $stats->sourceNames($period, ['search']);
        $social = $stats->sourceNames($period, ['social', 'ai']);
        $sites = $stats->sourceNames($period, ['referral', 'campaign']);
        $countries = $stats->grouped($period, 'country_code');
        $devices = $stats->grouped($period, 'device');
        $deviceLabels = ['desktop' => 'Computer', 'mobile' => 'Smartphone', 'tablet' => 'Tablet'];
        $n = fn (int|float $value): string => number_format($value, is_float($value) ? 1 : 0, ',', '.');
        [$from, $to] = $stats->range($period);
    @endphp

    <style>
        .jk-st-filter { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
        .jk-st-filter button { padding: .35rem .8rem; border-radius: 999px; font-size: .85rem; border: 1px solid rgb(0 0 0 / .12); }
        .dark .jk-st-filter button { border-color: rgb(255 255 255 / .15); }
        .jk-st-filter button.is-active { background: #e9204f; border-color: #e9204f; color: #fff; }
        .jk-st-tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); gap: 1rem; }
        .jk-st-tile-label { font-size: .8rem; opacity: .7; }
        .jk-st-tile-value { font-size: 1.75rem; font-weight: 700; line-height: 1.2; }
        .jk-st-chart { display: flex; align-items: flex-end; gap: 2px; height: 180px; padding-top: .5rem; border-bottom: 1px solid rgb(0 0 0 / .15); }
        .dark .jk-st-chart { border-color: rgb(255 255 255 / .2); }
        .jk-st-bar { flex: 1; height: 100%; display: flex; align-items: flex-end; position: relative; min-width: 3px; }
        .jk-st-bar span { display: block; width: 100%; background: #e9204f; border-radius: 4px 4px 0 0; min-height: 1px; }
        .jk-st-bar:hover span { background: #b8163c; }
        .jk-st-bar .jk-st-tip { display: none; position: absolute; bottom: calc(100% + 4px); left: 50%; transform: translateX(-50%); white-space: nowrap; background: #111; color: #fff; font-size: .75rem; padding: .35rem .5rem; border-radius: 6px; z-index: 5; pointer-events: none; }
        .jk-st-bar:hover .jk-st-tip { display: block; }
        .jk-st-axis { display: flex; justify-content: space-between; font-size: .75rem; opacity: .65; margin-top: .35rem; }
        .jk-st-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(20rem, 1fr)); gap: 1rem; }
        .jk-st-table { width: 100%; font-size: .875rem; border-collapse: collapse; }
        .jk-st-table th { text-align: left; font-weight: 600; font-size: .75rem; opacity: .7; padding: .35rem .25rem; }
        .jk-st-table td { padding: .4rem .25rem; border-top: 1px solid rgb(0 0 0 / .06); vertical-align: middle; }
        .dark .jk-st-table td { border-color: rgb(255 255 255 / .08); }
        .jk-st-table .n { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .jk-st-meter { height: 6px; background: rgb(0 0 0 / .06); border-radius: 3px; margin-top: .25rem; }
        .dark .jk-st-meter { background: rgb(255 255 255 / .1); }
        .jk-st-meter span { display: block; height: 100%; background: #e9204f; border-radius: 3px; }
        .jk-st-muted { font-size: .8rem; opacity: .65; }
    </style>

    <div class="jk-st-filter" role="group" aria-label="Periode">
        @foreach (\App\Support\Stats\VisitorStats::PERIODS as $key => $label)
            <button type="button" wire:click="setPeriod('{{ $key }}')" @class(['is-active' => $key === $period])>{{ $label }}</button>
        @endforeach
        <span class="jk-st-muted">{{ $from->translatedFormat('j M Y') }} - {{ $to->translatedFormat('j M Y') }}</span>
    </div>

    <div class="jk-st-tiles">
        <x-filament::section><div class="jk-st-tile-label">Bezoekers</div><div class="jk-st-tile-value">{{ $n($totals['visitors']) }}</div><div class="jk-st-muted">uniek per dag</div></x-filament::section>
        <x-filament::section><div class="jk-st-tile-label">Paginaweergaven</div><div class="jk-st-tile-value">{{ $n($totals['views']) }}</div><div class="jk-st-muted">{{ $n($totals['per_visitor']) }} per bezoeker</div></x-filament::section>
        <x-filament::section><div class="jk-st-tile-label">Via zoekmachines</div><div class="jk-st-tile-value">{{ $n($totals['search']) }}</div><div class="jk-st-muted">{{ $totals['entries'] ? round($totals['search'] / $totals['entries'] * 100) : 0 }}% van de binnenkomers</div></x-filament::section>
        <x-filament::section><div class="jk-st-tile-label">Binnenkomers</div><div class="jk-st-tile-value">{{ $n($totals['entries']) }}</div><div class="jk-st-muted">eerste pagina van een bezoek</div></x-filament::section>
    </div>

    <x-filament::section :heading="$period === '12m' ? 'Bezoekers per maand' : 'Bezoekers per dag'">
        @if ($totals['views'] === 0)
            <p class="jk-st-muted">Nog geen bezoeken in deze periode. Aangemelde beheerders en zoekrobots tellen niet mee.</p>
        @else
            <div class="jk-st-chart" role="img" aria-label="Staafdiagram van het aantal bezoekers">
                @foreach ($timeline as $point)
                    <div class="jk-st-bar">
                        <span style="height: {{ max(0.5, $point['visitors'] / $max * 100) }}%; {{ $point['visitors'] === 0 ? 'opacity: .25;' : '' }}"></span>
                        <div class="jk-st-tip"><strong>{{ $point['title'] }}</strong><br>{{ $n($point['visitors']) }} bezoekers · {{ $n($point['views']) }} weergaven</div>
                    </div>
                @endforeach
            </div>
            <div class="jk-st-axis">
                <span>{{ $timeline[0]['label'] ?? '' }}</span>
                <span>max. {{ $n($max) }}</span>
                <span>{{ end($timeline)['label'] ?? '' }}</span>
            </div>
        @endif
    </x-filament::section>

    <div class="jk-st-grid">
        <x-filament::section heading="Meest bekeken pagina's">
            <table class="jk-st-table">
                <thead><tr><th>Pagina</th><th class="n">Weergaven</th><th class="n">Bezoekers</th></tr></thead>
                <tbody>
                @forelse ($pages as $page)
                    <tr>
                        <td><a href="{{ url($page['path']) }}" target="_blank" rel="noopener" style="font-weight: 500;">{{ $page['title'] }}</a><div class="jk-st-muted">{{ $page['path'] }}</div></td>
                        <td class="n">{{ $n($page['views']) }}</td>
                        <td class="n">{{ $n($page['visitors']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="jk-st-muted">Nog geen gegevens.</td></tr>
                @endforelse
                </tbody>
            </table>
        </x-filament::section>

        <x-filament::section heading="Herkomst van de bezoeken">
            <table class="jk-st-table">
                <tbody>
                @foreach ($sources as $key => $count)
                    <tr>
                        <td>{{ \App\Support\Stats\VisitorStats::SOURCES[$key] }}<div class="jk-st-meter"><span style="width: {{ $count / $sourceTotal * 100 }}%"></span></div></td>
                        <td class="n">{{ $n($count) }}</td>
                        <td class="n jk-st-muted">{{ round($count / $sourceTotal * 100) }}%</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <p class="jk-st-muted" style="margin-top: .75rem;">Per binnenkomst (eerste pagina van een bezoek). Klikken binnen de site tellen hier niet.</p>
        </x-filament::section>

        <x-filament::section heading="Zoekmachines">
            <table class="jk-st-table">
                <tbody>
                @forelse ($engines as $name => $count)
                    <tr><td>{{ $name }}</td><td class="n">{{ $n($count) }}</td></tr>
                @empty
                    <tr><td class="jk-st-muted">Nog geen bezoeken via zoekmachines.</td></tr>
                @endforelse
                </tbody>
            </table>
        </x-filament::section>

        <x-filament::section heading="Sociale media en AI">
            <table class="jk-st-table">
                <tbody>
                @forelse ($social as $name => $count)
                    <tr><td>{{ $name }}</td><td class="n">{{ $n($count) }}</td></tr>
                @empty
                    <tr><td class="jk-st-muted">Nog geen bezoeken via sociale media.</td></tr>
                @endforelse
                </tbody>
            </table>
        </x-filament::section>

        <x-filament::section heading="Andere websites en campagnes">
            <table class="jk-st-table">
                <tbody>
                @forelse ($sites as $name => $count)
                    <tr><td>{{ $name }}</td><td class="n">{{ $n($count) }}</td></tr>
                @empty
                    <tr><td class="jk-st-muted">Nog geen verwijzende websites.</td></tr>
                @endforelse
                </tbody>
            </table>
        </x-filament::section>

        <x-filament::section heading="Landen en toestellen">
            <table class="jk-st-table">
                <thead><tr><th>Land</th><th class="n">Bezoekers</th></tr></thead>
                <tbody>
                @forelse ($countries as $code => $count)
                    <tr><td>{{ \App\Support\Security\GeoIp::label($code ?: null) }}</td><td class="n">{{ $n($count) }}</td></tr>
                @empty
                    <tr><td colspan="2" class="jk-st-muted">Nog geen gegevens.</td></tr>
                @endforelse
                </tbody>
            </table>
            <table class="jk-st-table" style="margin-top: 1rem;">
                <thead><tr><th>Toestel</th><th class="n">Bezoekers</th></tr></thead>
                <tbody>
                @foreach ($devices as $device => $count)
                    <tr><td>{{ $deviceLabels[$device] ?? 'Onbekend' }}</td><td class="n">{{ $n($count) }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </x-filament::section>
    </div>

    <p class="jk-st-muted">Privacyvriendelijk: geen cookies en geen IP-adressen bewaard. Een bezoeker telt één keer per dag. Aangemelde beheerders en zoekrobots tellen niet mee. Landen via <a href="https://db-ip.com" target="_blank" rel="noopener">DB-IP</a>.</p>
</x-filament-panels::page>
