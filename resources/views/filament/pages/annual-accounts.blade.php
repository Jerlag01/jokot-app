<x-filament-panels::page>
    @php
        $accounts = app(\App\Support\AnnualAccounts::class);
        $export = app(\App\Support\AnnualAccountsExport::class);
        $years = $this->years();
        $selected = $this->selectedYear();
        $overview = $accounts->overview($years);
        $details = $accounts->actuals($selected);
        $fmt = fn (float $value): string => number_format($value, 2, ',', '.');
    @endphp

    <x-filament::section>
        <div style="overflow-x: auto;">
            <table class="jk-acc">
                <thead>
                <tr>
                    <th style="text-align: left;">{{ mb_strtoupper(settings()->get('legal_name')) }}</th>
                    @foreach ($years as $year)
                        <th colspan="2" @class(['is-selected' => $year === $selected])>
                            <button type="button" wire:click="$set('year', {{ $year }})">{{ $year }}</button>
                        </th>
                    @endforeach
                </tr>
                <tr>
                    <th></th>
                    @foreach ($years as $year)
                        <th class="n">Budget</th><th class="n">Jaarrekening</th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @foreach (\App\Support\AnnualAccounts::layout() as $row)
                    <tr class="jk-acc-{{ $row['type'] }}">
                        @if (in_array($row['type'], ['heading', 'section'], true))
                            <td colspan="{{ 1 + 2 * count($years) }}">{{ $row['label'] }}</td>
                        @else
                            <td>
                                @if ($row['type'] === 'line' && ! empty($details[$row['key']]['items']))
                                    <button type="button" wire:click="toggleLine('{{ $row['key'] }}')" style="text-decoration: underline dotted;" title="Toon de boekingen van {{ $selected }}">{{ $row['label'] }}</button>
                                    <span style="opacity: .5; font-size: .75rem;">({{ count($details[$row['key']]['items']) }})</span>
                                @else
                                    {{ $row['label'] }}
                                @endif
                            </td>
                            @foreach ($years as $year)
                                @php([$budget, $actual] = $export->cells($overview[$year], $row))
                                <td class="n jk-acc-budget">{{ $fmt($budget) }}</td>
                                <td @class(['n', 'jk-acc-actual', 'is-selected' => $year === $selected, 'is-negative' => $actual < 0])>{{ $fmt($actual) }}</td>
                            @endforeach
                        @endif
                    </tr>
                    @if ($row['type'] === 'line' && $this->openLine === $row['key'] && ! empty($details[$row['key']]['items']))
                        <tr class="jk-acc-details">
                            <td colspan="{{ 1 + 2 * count($years) }}">
                                <table style="width: 100%;">
                                    @foreach ($details[$row['key']]['items'] as $item)
                                        <tr>
                                            <td style="width: 7rem;">{{ $item['date'] ? \Illuminate\Support\Carbon::parse($item['date'])->format('d/m/Y') : '' }}</td>
                                            <td style="width: 14rem;">@if ($item['url'])<a href="{{ $item['url'] }}" style="text-decoration: underline;">{{ $item['source'] }}</a>@else{{ $item['source'] }}@endif</td>
                                            <td>{{ $item['description'] }}</td>
                                            <td class="n">{{ $fmt($item['amount']) }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                        </tr>
                    @endif
                @endforeach
                </tbody>
            </table>
        </div>
        <p style="margin-top: 1rem; font-size: .8rem; opacity: .7;">
            Klik op een lijn om de boekingen van {{ $selected }} te zien. Uitgaven komen uit de inkomende facturen (volgens de kostencategorie),
            ontvangsten uit de facturen, toogkassa, ticketverkoop en ledenlijst. Andere bedragen (subsidie, vrijwilligersvergoedingen, cash zonder factuur) voeg je toe bij "Manuele boekingen".
        </p>
    </x-filament::section>

    <style>
        .jk-acc { width: 100%; border-collapse: collapse; font-size: .85rem; }
        .jk-acc th { padding: .4rem .6rem; font-weight: 600; border-bottom: 2px solid rgba(127,127,127,.35); white-space: nowrap; }
        .jk-acc th.is-selected { color: var(--jk-accent); }
        .jk-acc td { padding: .3rem .6rem; border-bottom: 1px solid rgba(127,127,127,.15); }
        .jk-acc .n { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .jk-acc-budget { opacity: .65; }
        .jk-acc-actual.is-selected { background: rgba(233,32,79,.07); font-weight: 600; }
        .jk-acc-actual.is-negative { color: #dc2626; }
        .jk-acc-heading td { font-weight: 800; font-size: 1rem; padding-top: 1.25rem; border: 0; }
        .jk-acc-section td { font-weight: 700; color: var(--jk-accent); padding-top: .9rem; border: 0; }
        .jk-acc-subtotal td { font-weight: 700; background: rgba(127,127,127,.08); }
        .jk-acc-total td { font-weight: 700; background: rgba(127,127,127,.16); }
        .jk-acc-result td, .jk-acc-cumulated td { font-weight: 800; background: rgba(233,32,79,.12); }
        .jk-acc-details td { background: rgba(127,127,127,.05); font-size: .8rem; }
    </style>
</x-filament-panels::page>
