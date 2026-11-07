@php($logo = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/jokot-logo-zwart.png'))))
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>Tickets</title>
    @include('pdf._style')
    <style>
        .ticket { border: 0.6mm solid #111; border-radius: 4mm; padding: 8mm; }
        .ticket h1 { font-size: 20pt; }
        .code { font-family: 'DejaVu Sans Mono', monospace; font-size: 15pt; letter-spacing: 1mm; }
    </style>
</head>
<body>
@foreach ($tickets as $ticket)
    <div class="ticket">
        <table>
            <tr>
                <td style="vertical-align: top;">
                    <img src="{{ $logo }}" style="width: 18mm; height: 18mm;" alt="">
                    <h1 style="margin-top: 4mm;">{{ $ticket->event->title }}</h1>
                    <p style="font-size: 12pt;">{{ ucfirst($ticket->event->starts_at->translatedFormat('l j F Y')) }} - {{ $ticket->event->starts_at->format('H:i') }}</p>
                    <p>{{ $settings->siteName() }}<br>{{ $settings->get('address') }}</p>
                    <p style="margin-top: 6mm;"><span class="muted">Ticket</span><br><strong style="font-size: 13pt;">{{ $ticket->ticketType->name }}</strong></p>
                    <p><span class="muted">Op naam van</span><br>{{ $ticket->holder_name }}</p>
                    <p class="small muted">Bestelling {{ $ticket->order->number }}</p>
                </td>
                <td style="width: 70mm; text-align: center; vertical-align: top;">
                    <img src="{{ $qrs[$ticket->id] }}" style="width: 64mm; height: 64mm;" alt="">
                    <div class="code">{{ $ticket->displayCode() }}</div>
                </td>
            </tr>
        </table>
        <p class="small muted" style="margin-top: 6mm;">Deze code geeft één keer toegang. Deel ze niet en zet ze niet online. Er gelden onze algemene voorwaarden ({{ url('/algemene-voorwaarden') }}).</p>
    </div>
    @unless ($loop->last)
        <div class="page-break"></div>
    @endunless
@endforeach
</body>
</html>
