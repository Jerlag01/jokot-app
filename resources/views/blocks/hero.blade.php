{{-- Full-width photo with a dark overlay, as the "Welkom bij JH Jokot" banner. --}}
<section @class(['hero', 'hero-small' => ($data['size'] ?? 'large') === 'small']) style="--hero-image: url('{{ media_url($data['image'] ?? null) ?? asset('images/zaal-rook.jpg') }}')">
    <div class="container hero-inner">
        @if (! empty($data['eyebrow']))
            <p class="hero-eyebrow">{{ $data['eyebrow'] }}</p>
        @endif
        <h1 class="hero-title">{{ $data['heading'] ?? $page->title }}</h1>
        @if (! empty($data['text']))
            <p class="hero-text">{{ $data['text'] }}</p>
        @endif
        @if (! empty($data['button_label']) && ! empty($data['button_url']))
            <a class="button" href="{{ $data['button_url'] }}">{{ $data['button_label'] }}</a>
        @endif
    </div>
</section>
