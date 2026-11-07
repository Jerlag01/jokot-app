<x-mail::message>
Beste {{ $contract->contractable->contractSignerName() }},

@if ($signedCopy)
Bedankt! In bijlage vind je het ondertekende contract "{{ $contract->title() }}" ({{ $contract->contractable->reference }}), ondertekend op {{ $contract->signed_at->format('d/m/Y \o\m H:i') }}.
@else
Hierbij het contract "{{ $contract->title() }}" ({{ $contract->contractable->reference }}). Je kan het online lezen en ondertekenen via de knop hieronder.

<x-mail::button :url="$contract->signUrl()">
Contract lezen en ondertekenen
</x-mail::button>
@endif

Vragen? Antwoord gewoon op deze mail.

Met vriendelijke groeten,<br>
{{ settings()->siteName() }}
</x-mail::message>
