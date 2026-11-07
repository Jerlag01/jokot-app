@php($logo = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/jokot-logo-zwart.png'))))
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->displayNumber() }}</title>
    @include('pdf._style')
</head>
<body>
<div class="footer">
    {{ $settings->get('legal_name') }} - {{ $settings->get('address') }} - Ondernemingsnr. {{ $settings->get('company_number') }}@if ($settings->get('vat_number')) - Btw {{ $settings->get('vat_number') }}@endif
    - {{ $settings->get('iban') }}@if ($settings->get('bic')) ({{ $settings->get('bic') }})@endif - {{ $settings->get('email') }}
</div>

<table class="header">
    <tr>
        <td style="width: 26mm;"><img class="logo" src="{{ $logo }}" alt=""></td>
        <td>
            <strong style="font-size: 12pt;">{{ $settings->get('legal_name') }}</strong><br>
            {{ $settings->siteName() }}<br>
            {{ $settings->get('address') }}<br>
            Ondernemingsnr. {{ $settings->get('company_number') }}@if ($settings->get('vat_number'))<br>Btw {{ $settings->get('vat_number') }}@endif<br>
            {{ $settings->get('email') }}
        </td>
        <td style="width: 70mm;">
            <div class="box">
                <strong>{{ $invoice->customer->name }}</strong><br>
                @if ($invoice->customer->contact_name)t.a.v. {{ $invoice->customer->contact_name }}<br>@endif
                @foreach ($invoice->customer->addressLines() as $line){{ $line }}<br>@endforeach
                @if ($invoice->customer->vat_number)Btw/ondernemingsnr. {{ $invoice->customer->vat_number }}@endif
            </div>
        </td>
    </tr>
</table>

<h1 style="margin-top: 10mm;">{{ $invoice->isCreditNote() ? 'Creditnota' : 'Factuur' }} <span class="accent">{{ $invoice->displayNumber() }}</span></h1>
@if ($invoice->isDraft())
    <p class="accent"><strong>CONCEPT - geen geldige factuur</strong></p>
@endif

<table style="margin-bottom: 5mm;">
    <tr>
        <td><span class="muted">Datum</span><br>{{ $invoice->issue_date->format('d/m/Y') }}</td>
        @unless ($invoice->isCreditNote())
            <td><span class="muted">Vervaldatum</span><br>{{ $invoice->due_date->format('d/m/Y') }}</td>
        @endunless
        @if ($invoice->creditedInvoice)
            <td><span class="muted">Crediteert factuur</span><br>{{ $invoice->creditedInvoice->number }} ({{ $invoice->creditedInvoice->issue_date->format('d/m/Y') }})</td>
        @endif
        @if ($invoice->structured_reference && ! $invoice->isCreditNote())
            <td><span class="muted">Gestructureerde mededeling</span><br><strong>{{ $invoice->structured_reference }}</strong></td>
        @endif
    </tr>
</table>

@if ($invoice->subject)
    <p><strong>{{ $invoice->subject }}</strong></p>
@endif

<table class="lines">
    <thead>
    <tr>
        <th>Omschrijving</th>
        <th class="right" style="width: 18mm;">Aantal</th>
        <th class="right" style="width: 24mm;">Eenheidsprijs</th>
        <th class="right" style="width: 14mm;">Btw</th>
        <th class="right" style="width: 26mm;">Totaal excl.</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($invoice->lines as $line)
        <tr>
            <td>{{ $line->description }}</td>
            <td class="right">{{ rtrim(rtrim(number_format((float) $line->quantity, 2, ',', '.'), '0'), ',') }}</td>
            <td class="right">{{ euro($line->unit_price) }}</td>
            <td class="right">{{ rtrim(rtrim(number_format((float) $line->vat_rate, 2, ',', ''), '0'), ',') }}%</td>
            <td class="right">{{ euro($line->line_total) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table style="margin-top: 4mm;">
    <tr>
        <td style="width: 55%; vertical-align: top;">
            @if ($paymentQr)
                <table>
                    <tr>
                        <td style="width: 30mm;"><img src="{{ $paymentQr }}" style="width: 28mm; height: 28mm;" alt=""></td>
                        <td class="small">
                            <strong>Betalen</strong><br>
                            {{ euro($invoice->balance()) }} op {{ $settings->get('iban') }}<br>
                            met mededeling {{ $invoice->structured_reference ?? $invoice->displayNumber() }}<br>
                            voor {{ $invoice->due_date->format('d/m/Y') }}.<br>
                            <span class="muted">Scan de code met je bankapp.</span>
                        </td>
                    </tr>
                </table>
            @endif
        </td>
        <td style="vertical-align: top;">
            <table class="totals">
                <tr><td>Subtotaal</td><td class="right">{{ euro($invoice->subtotal) }}</td></tr>
                @foreach ($invoice->vatSummary() as $rate => $amounts)
                    <tr><td>Btw {{ rtrim(rtrim(number_format((float) $rate, 2, ',', ''), '0'), ',') }}% op {{ euro($amounts['base']) }}</td><td class="right">{{ euro($amounts['vat']) }}</td></tr>
                @endforeach
                <tr class="grand"><td>Totaal</td><td class="right">{{ euro($invoice->total) }}</td></tr>
                @if ((float) $invoice->paid_total > 0 && ! $invoice->isCreditNote())
                    <tr><td>Reeds betaald</td><td class="right">{{ euro($invoice->paid_total) }}</td></tr>
                    <tr><td><strong>Te betalen</strong></td><td class="right"><strong>{{ euro($invoice->balance()) }}</strong></td></tr>
                @endif
            </table>
        </td>
    </tr>
</table>

@if ($settings->get('vat_note'))
    <p class="small muted" style="margin-top: 5mm;">{{ $settings->get('vat_note') }}</p>
@endif
@if ($invoice->notes)
    <p style="margin-top: 4mm;">{!! nl2br(e($invoice->notes)) !!}</p>
@endif
@if ($settings->get('invoice_footer'))
    <p class="small muted" style="margin-top: 4mm;">{!! nl2br(e($settings->get('invoice_footer'))) !!}</p>
@endif
</body>
</html>
