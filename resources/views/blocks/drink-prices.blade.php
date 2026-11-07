@php($drinks = \App\Models\Product::query()->active()->whereNotNull('bar_price')->get())
@if ($drinks->isNotEmpty())
    <section class="section section-tight">
        <div class="container narrow">
            @if (! empty($data['heading']))
                <h2>{{ $data['heading'] }}</h2>
            @endif
            <ul class="price-list">
                @foreach ($drinks as $drink)
                    <li><span>{{ $drink->name }}</span><span>{{ euro($drink->bar_price) }}</span></li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
