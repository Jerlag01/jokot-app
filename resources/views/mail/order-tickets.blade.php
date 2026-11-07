<x-mail::message>
# Je tickets voor {{ $order->event->title }}

Hallo {{ $order->name }},

Bedankt voor je bestelling! In bijlage vind je je tickets ({{ $order->tickets()->valid()->count() }}). Toon de QR-code aan de ingang, op je gsm of afgedrukt. Elke code geeft één keer toegang.

<x-mail::table>
| | |
|:--|:--|
| Evenement | {{ $order->event->title }} |
| Wanneer | {{ ucfirst($order->event->starts_at->translatedFormat('l j F Y')) }} om {{ $order->event->starts_at->format('H:i') }} |
| Waar | {{ settings()->get('address') }} |
| Bestelling | {{ $order->number }} |
| Totaal | {{ euro($order->total) }} |
</x-mail::table>

<x-mail::button :url="$order->publicUrl()">
Bestelling en tickets bekijken
</x-mail::button>

Tot dan!<br>
{{ settings()->siteName() }}
</x-mail::message>
