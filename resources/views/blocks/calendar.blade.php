@php
    $requested = request('maand');
    $month = (is_string($requested) && preg_match('/^\d{4}-\d{2}$/', $requested))
        ? \Carbon\CarbonImmutable::createFromFormat('!Y-m', $requested)
        : null;
    $withForm = $page->hasBlock('rental_form');
@endphp
<section class="section section-tight" id="kalender">
    <div class="container narrow">
        @if (! empty($data['heading']))
            <h2>{{ $data['heading'] }}</h2>
        @endif
        @if (! empty($data['intro']))
            <p>{{ $data['intro'] }}</p>
        @endif
        <div class="calendar">
            <div class="calendar-body" data-calendar data-calendar-url="{{ route('calendar', ['form' => $withForm ? 1 : 0]) }}">
                @include('partials.calendar-grid', ['month' => $month, 'withForm' => $withForm])
            </div>
            <div class="calendar-legend">
                <span><i class="dot is-free"></i> Vrij</span>
                <span><i class="dot is-taken"></i> Verhuurd</span>
                <span><i class="dot is-event"></i> Eigen activiteit</span>
                <span><i class="dot is-closed"></i> Niet reserveerbaar</span>
            </div>
        </div>
    </div>
</section>
