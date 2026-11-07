@php($r = $rentalRequest->loadMissing('drinks', 'rentalRate', 'rentalExtra'))
<x-mail::message>
@if ($forBoard)
# Nieuwe huuraanvraag

**{{ $r->fullName() }}**{{ $r->organisation ? ' ('.$r->organisation.')' : '' }} vraagt het jeugdhuis aan voor **{{ $r->event_date->translatedFormat('l j F Y') }}**.
@else
# Bedankt voor je aanvraag

Hallo {{ $r->first_name }},

We hebben je aanvraag om het jeugdhuis te huren op **{{ $r->event_date->translatedFormat('l j F Y') }}** goed ontvangen. Het bestuur bekijkt ze zo snel mogelijk en neemt contact met je op. Let op: je aanvraag is pas definitief na onze bevestiging.
@endif

<x-mail::table>
| | |
|:--|:--|
| Referentie | {{ $r->reference }} |
| Datum | {{ $r->event_date->format('d/m/Y') }} |
| Gebruikersgroep | {{ $r->rentalRate?->name ?? '-' }} |
| DJ-tafel | {{ $r->rentalExtra?->name ?? 'Nee' }} |
| Verwacht aantal | {{ $r->expected_guests ?? '-' }} |
| Eigen wijn/cava/champagne | {{ $r->brings_own_wine ? 'Ja' : 'Nee' }} |
@if ($r->quoted_price !== null)
| Richtprijs huur | {{ euro($r->quoted_price) }} (waarborg {{ euro($r->quoted_deposit) }}) |
@endif
| E-mail | {{ $r->email }} |
| Telefoon | {{ $r->phone }} |
| Adres | {{ $r->address() }} |
</x-mail::table>

@if ($r->drinks->isNotEmpty())
**Drank**

@foreach ($r->drinks as $line)
- {{ $line->quantity }} × {{ $line->name }}
@endforeach
@endif

@if ($r->details)
**Details**

{{ $r->details }}
@endif

@if ($forBoard)
<x-mail::button :url="url('/admin/rental-requests/'.$r->id)">
Aanvraag openen
</x-mail::button>
@endif

{{ settings()->siteName() }}<br>
{{ settings()->get('address') }}
</x-mail::message>
