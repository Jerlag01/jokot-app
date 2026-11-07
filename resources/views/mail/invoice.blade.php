<x-mail::message>
@if ($reminder)
# Herinnering

Beste {{ $invoice->customer->contact_name ?: $invoice->customer->name }},

Volgens onze gegevens is {{ $invoice->isCreditNote() ? 'creditnota' : 'factuur' }} **{{ $invoice->number }}** van {{ $invoice->issue_date->format('d/m/Y') }} nog niet (volledig) betaald. Het openstaande bedrag is **{{ euro($invoice->balance()) }}**, te betalen sinds {{ $invoice->due_date->format('d/m/Y') }}. Heb je intussen betaald? Dan mag je deze herinnering negeren.
@else
Beste {{ $invoice->customer->contact_name ?: $invoice->customer->name }},

In bijlage vind je {{ $invoice->isCreditNote() ? 'creditnota' : 'factuur' }} **{{ $invoice->number }}**@if ($invoice->subject) ({{ $invoice->subject }})@endif.
@endif

@unless ($invoice->isCreditNote())
<x-mail::table>
| | |
|:--|:--|
| Bedrag | {{ euro($invoice->balance()) }} |
| Te betalen voor | {{ $invoice->due_date->format('d/m/Y') }} |
| Rekeningnummer | {{ settings()->get('iban') }} |
| Mededeling | {{ $invoice->structured_reference }} |
</x-mail::table>
@endunless

Met vriendelijke groeten,<br>
{{ settings()->siteName() }} - {{ settings()->get('legal_name') }}
</x-mail::message>
