<section class="section section-tight" @if (! empty($data['anchor'])) id="{{ $data['anchor'] }}" @endif>
    <div class="container narrow prose">
        @if (! empty($data['heading']))
            <h2>{{ $data['heading'] }}</h2>
        @endif
        {{ rich_text($data['content'] ?? '') }}
    </div>
</section>
