@extends('layouts.site', ['title' => 'Bestelling '.$order->number])

@section('content')
    <section class="page-header">
        <div class="container">
            <p class="eyebrow">Bestelling {{ $order->number }}</p>
            <h1>{{ $order->event->title }}</h1>
        </div>
    </section>
    <section class="section">
        <div class="container narrow">
            @if ($order->isPaid())
                <div class="alert alert-success" role="status">
                    <strong>Betaald, je tickets zijn klaar!</strong>
                    We stuurden ze ook naar {{ $order->email }}. Toon de QR-code aan de ingang.
                </div>
                <p><a class="button" href="{{ route('orders.tickets', $order) }}">Download je tickets (PDF)</a></p>
            @elseif ($order->isPending())
                @if ($cancelledAtProvider)
                    <div class="alert">Je hebt de betaling afgebroken. Je tickets worden nog tot {{ $order->expires_at->format('H:i') }} vastgehouden.</div>
                @else
                    <div class="alert" role="status">
                        <strong>We wachten nog op de bevestiging van je betaling.</strong>
                        Dit duurt meestal maar enkele seconden. <a href="{{ $order->publicUrl() }}">Vernieuw deze pagina</a>.
                    </div>
                @endif
                @if ($order->expires_at?->isFuture() && $order->provider?->isOnline())
                    <form method="post" action="{{ route('orders.pay', $order) }}">
                        @csrf
                        <button type="submit" class="button">Verder betalen</button>
                    </form>
                @endif
            @else
                <div class="alert alert-error">Deze bestelling is <strong>{{ strtolower($order->status->getLabel()) }}</strong>. Er werden geen tickets uitgereikt@if ($order->status === \App\Enums\OrderStatus::Refunded) of ze zijn terugbetaald@endif.</div>
                <p><a href="{{ route('events.show', $order->event) }}">Opnieuw bestellen</a></p>
            @endif

            @error('payment')<div class="alert alert-error">{{ $message }}</div>@enderror

            <table class="table" style="margin-top: 2rem;">
                <thead><tr><th>Ticket</th><th class="num">Aantal</th><th class="num">Prijs</th></tr></thead>
                <tbody>
                @foreach ($order->items as $item)
                    <tr><td>{{ $item->name }}</td><td class="num">{{ $item->quantity }}</td><td class="num">{{ euro($item->quantity * (float) $item->unit_price) }}</td></tr>
                @endforeach
                <tr><td><strong>Totaal</strong></td><td></td><td class="num"><strong>{{ euro($order->total) }}</strong></td></tr>
                </tbody>
            </table>
            <p class="muted small">{{ ucfirst($order->event->starts_at->translatedFormat('l j F Y')) }} om {{ $order->event->starts_at->format('H:i') }} &middot; {{ settings()->get('address') }}</p>
        </div>
    </section>
@endsection
