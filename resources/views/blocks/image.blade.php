@if ($image = media_url($data['image'] ?? null))
    <section class="section section-tight">
        <figure class="container narrow figure">
            <img src="{{ $image }}" alt="{{ $data['caption'] ?? '' }}" loading="lazy">
            @if (! empty($data['caption']))
                <figcaption>{{ $data['caption'] }}</figcaption>
            @endif
        </figure>
    </section>
@endif
