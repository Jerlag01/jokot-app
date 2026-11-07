@php($events = \App\Models\Event::query()->published()->upcoming()->limit((int) ($data['limit'] ?? 6))->get())
<section class="section" id="agenda">
    <div class="container">
        @if (! empty($data['heading']))
            <h2 class="section-title">{{ $data['heading'] }}</h2>
        @endif
        @if ($events->isEmpty())
            <p class="muted">{{ $data['empty_text'] ?? 'Er staan momenteel geen activiteiten gepland. Volg ons op Facebook en Instagram om niets te missen!' }}</p>
        @else
            <div class="event-grid">
                @foreach ($events as $event)
                    <a class="event-card" href="{{ route('events.show', $event) }}">
                        <div class="event-card-image" style="--card-image: url('{{ $event->imageUrl() ?? asset('images/fuif.jpg') }}')">
                            <span class="event-date"><strong>{{ $event->starts_at->format('j') }}</strong>{{ $event->starts_at->translatedFormat('M') }}</span>
                        </div>
                        <div class="event-card-body">
                            <h3>{{ $event->title }}</h3>
                            <p class="muted small">{{ ucfirst($event->starts_at->translatedFormat('l')) }} &middot; {{ $event->starts_at->format('H:i') }}@if ($event->price_label) &middot; {{ $event->price_label }}@endif</p>
                            @if ($event->tickets_enabled)
                                <span class="badge badge-accent">Tickets online</span>
                            @endif
                            @if ($event->summary)
                                <p>{{ \Illuminate\Support\Str::limit($event->summary, 120) }}</p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</section>
