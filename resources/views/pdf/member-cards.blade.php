<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>Lidkaarten {{ $year }}</title>
    <style>
        @page { margin: {{ $layout === 'card' ? '0' : '12mm 19mm' }}; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; }
        .card { position: relative; width: 85.6mm; height: 54mm; background: #0e0e0e; color: #fff; overflow: hidden; }
        .a4 .card { position: absolute; border-radius: 3mm; }
        .stripe { position: absolute; left: 0; right: 0; bottom: 0; height: 2.2mm; background: #e9204f; }
        .logo { position: absolute; left: 4mm; top: 4mm; width: 15mm; height: 15mm; }
        .title { position: absolute; left: 21.5mm; top: 5mm; font-size: 6.5pt; letter-spacing: .6mm; text-transform: uppercase; color: #e9204f; font-weight: bold; }
        .org { position: absolute; left: 21.5mm; top: 9mm; font-size: 6.5pt; color: #bbb; }
        .year { position: absolute; left: 21.5mm; top: 12.5mm; font-size: 15pt; font-weight: bold; }
        .label { font-size: 5pt; text-transform: uppercase; letter-spacing: .3mm; color: #999; }
        .name { position: absolute; left: 4mm; top: 25mm; width: 50mm; }
        .name .value { font-size: 10.5pt; font-weight: bold; line-height: 1.15; }
        .first { position: absolute; left: 4mm; top: 36.5mm; width: 50mm; }
        .first .value { font-size: 9pt; line-height: 1.15; }
        .number { position: absolute; left: 4mm; bottom: 4mm; font-size: 5.5pt; color: #999; }
        .qr { position: absolute; right: 3.5mm; top: 15mm; width: 26mm; height: 26mm; background: #fff; padding: 1mm; }
        .qr img { width: 26mm; height: 26mm; }
        .qr-label { position: absolute; right: 3.5mm; top: 43.5mm; width: 28mm; text-align: center; font-size: 4.5pt; color: #999; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body class="{{ $layout }}">
@foreach ($cards->chunk($layout === 'card' ? 1 : 10) as $page)
    @foreach ($page->values() as $index => $card)
        @php($member = $card['member'])
        <div class="card" @if ($layout === 'a4') style="left: {{ ($index % 2) * 88 }}mm; top: {{ intdiv($index, 2) * 55 }}mm;" @endif>
            <img class="logo" src="{{ $logo }}" alt="">
            <div class="title">Lidkaart</div>
            <div class="org">JH Jokot - Niel</div>
            <div class="year">{{ $year }}</div>
            <div class="name"><div class="label">Naam</div><div class="value">{{ $member->last_name }}</div></div>
            <div class="first"><div class="label">Voornaam</div><div class="value">{{ $member->first_name }}</div></div>
            <div class="number">Lid {{ $member->member_number }}</div>
            <div class="qr"><img src="{{ $card['qr'] }}" alt=""></div>
            <div class="qr-label">Scan om je gegevens aan te passen</div>
            <div class="stripe"></div>
        </div>
        @if ($layout === 'card' && ! $loop->parent->last)
            <div class="page-break"></div>
        @endif
    @endforeach
    @if ($layout === 'a4' && ! $loop->last)
        <div class="page-break"></div>
    @endif
@endforeach
</body>
</html>
