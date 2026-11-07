<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>Jaarrekening</title>
    @include('pdf._style')
    <style>
        @page { margin: 12mm 12mm 14mm; }
        body { font-size: 8pt; }
        .acc th { background: #111; color: #fff; padding: 1.2mm 1.5mm; font-size: 7.5pt; }
        .acc td { padding: .9mm 1.5mm; border-bottom: .2mm solid #e3e3e3; }
        .acc td.n { text-align: right; white-space: nowrap; }
        .acc tr.heading td { font-weight: bold; font-size: 9.5pt; padding-top: 3mm; border: 0; }
        .acc tr.section td { font-weight: bold; padding-top: 2mm; border: 0; color: #e9204f; }
        .acc tr.subtotal td { font-weight: bold; background: #f5f5f5; }
        .acc tr.total td { font-weight: bold; background: #e9e9e9; }
        .acc tr.result td, .acc tr.cumulated td { font-weight: bold; background: #111; color: #fff; }
        .acc .actual { background: #fafafa; }
        .neg { color: #c21641; }
    </style>
</head>
<body>
<div class="footer">{{ $settings->get('legal_name') }} - jaarrekening - afgedrukt op {{ now()->format('d/m/Y H:i') }}</div>
<h1 style="font-size: 14pt;">{{ mb_strtoupper($settings->get('legal_name')) }} - Jaarrekening {{ implode(' / ', $years) }}</h1>
<table class="acc">
    <thead>
    <tr>
        <th style="text-align: left;">BUDGET</th>
        @foreach ($years as $year)
            <th colspan="2" style="text-align: center;">{{ $year }}</th>
        @endforeach
    </tr>
    <tr>
        <th></th>
        @foreach ($years as $year)
            <th style="text-align: right;">Budget/Schatting</th><th style="text-align: right;">Jaarrekening</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @foreach ($layout as $row)
        <tr class="{{ $row['type'] }}">
            <td>{{ $row['label'] }}</td>
            @foreach ($years as $year)
                @if (in_array($row['type'], ['heading', 'section'], true))
                    <td colspan="2"></td>
                @else
                    @php([$budget, $actual] = $export->cells($overview[$year], $row))
                    <td class="n">{{ number_format($budget, 2, ',', '.') }}</td>
                    <td class="n actual @if ($actual < 0) neg @endif">{{ number_format($actual, 2, ',', '.') }}</td>
                @endif
            @endforeach
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
