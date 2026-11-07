@php($logo = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/jokot-logo-zwart.png'))))
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>{{ $contract->title() }}</title>
    @include('pdf._style')
    <style>
        .contract table { width: 100%; border-collapse: collapse; margin: 2mm 0 4mm; }
        .contract th, .contract td { border: 0.3mm solid #bbb; padding: 1.5mm 2mm; text-align: left; vertical-align: top; }
        .contract th { background: #f0f0f0; }
        .contract ul { margin: 0 0 3mm; padding-left: 5mm; }
    </style>
</head>
<body>
<div class="footer">{{ $settings->get('legal_name') }} - {{ $settings->get('address') }} - {{ $contract->contractable->reference }}@if ($contract->isSigned()) - digitaal ondertekend op {{ $contract->signed_at->format('d/m/Y H:i') }}@endif</div>

<table class="header">
    <tr>
        <td style="width: 26mm;"><img class="logo" src="{{ $logo }}" alt=""></td>
        <td style="vertical-align: middle;"><h1 style="margin: 0;">{{ $contract->title() }}</h1><span class="muted">Referentie {{ $contract->contractable->reference }}</span></td>
    </tr>
</table>

<div class="contract" style="margin-top: 6mm;">{!! $body !!}</div>

<table style="margin-top: 10mm; page-break-inside: avoid;">
    <tr>
        <td style="width: 50%; vertical-align: top;">
            <strong>Voor {{ $settings->get('legal_name') }}</strong><br>
            {{ $settings->get('contract_representative') ?: '................................' }}
        </td>
        <td style="vertical-align: top;">
            <strong>De huurder</strong><br>
            @if ($contract->isSigned())
                {{ $contract->signer_name }}<br>
                @if ($signature)<img src="{{ $signature }}" style="height: 22mm; margin: 2mm 0;" alt="Handtekening">@endif<br>
                <span class="small muted">Digitaal ondertekend op {{ $contract->signed_at->format('d/m/Y \o\m H:i') }}@if ($contract->signer_ip) vanaf {{ $contract->signer_ip }}@endif</span>
            @else
                {{ $contract->contractable->contractSignerName() }}<br><br><br>
                <span class="muted">Handtekening: ................................ Datum: ..../..../........</span>
            @endif
        </td>
    </tr>
</table>
</body>
</html>
