@extends('layouts.site', ['title' => $event->title, 'description' => $event->summary])

@section('content')
    <section class="page-header @if ($event->imageUrl()) has-image @endif" @if ($event->imageUrl()) style="--header-image: url('{{ $event->imageUrl() }}')" @endif>
        <div class="container">
            <p class="eyebrow">{{ ucfirst($event->starts_at->translatedFormat('l j F Y')) }} &middot; {{ $event->starts_at->format('H:i') }}@if ($event->ends_at) - {{ $event->ends_at->format('H:i') }}@endif</p>
            <h1>{{ $event->title }}</h1>
        </div>
    </section>
    <section class="section">
        <div class="container narrow prose">
            @if ($event->summary)
                <p class="lead">{{ $event->summary }}</p>
            @endif
            {{ rich_text($event->description) }}
            @if ($event->price_label || $event->ticket_url)
                <div class="event-tickets">
                    @if ($event->price_label)
                        <span class="badge">{{ $event->price_label }}</span>
                    @endif
                    @if ($event->ticket_url && ! $event->tickets_enabled)
                        <a class="button" href="{{ $event->ticket_url }}" target="_blank" rel="noopener">Tickets</a>
                    @endif
                </div>
            @endif
        </div>
    </section>

    @if ($event->tickets_enabled)
        @include('events.partials.ticket-form')
    @endif

    <section class="section section-tight">
        <div class="container narrow"><a href="{{ route('home') }}">&larr; Terug naar de startpagina</a></div>
    </section>
@endsection
