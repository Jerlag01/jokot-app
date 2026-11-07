{{-- Photo next to a short call to action ("Verhuren - Je event bij ons organiseren? Kan zeker!"). --}}
<section class="section">
    <div @class(['container', 'cta', 'cta-reverse' => ! empty($data['image_right'])])>
        @if ($image = media_url($data['image'] ?? null))
            <div class="cta-image"><img src="{{ $image }}" alt="" loading="lazy"></div>
        @endif
        <div class="cta-body">
            <h2>{{ $data['heading'] ?? '' }}</h2>
            @if (! empty($data['text']))
                <p class="lead">{{ $data['text'] }}</p>
            @endif
            @if (! empty($data['button_label']) && ! empty($data['button_url']))
                <a class="button" href="{{ $data['button_url'] }}">{{ $data['button_label'] }}</a>
            @endif
        </div>
    </div>
</section>
