{{-- The price table in the rental contract, from the current rates, extras and renter prices of the products. --}}
<table>
    <thead><tr><th>Voorziening</th><th style="width: 30%;">Prijs (in euro)</th></tr></thead>
    <tbody>
    <tr><th colspan="2">Accommodatie</th></tr>
    @foreach (\App\Models\RentalRate::query()->active()->get() as $rate)
        <tr><td>Zaal {{ $rate->name }}</td><td>{{ euro($rate->price) }}</td></tr>
    @endforeach
    @foreach (\App\Models\RentalExtra::query()->active()->get() as $extra)
        <tr><td>{{ $extra->name }} (op aanvraag)</td><td>{{ euro($extra->price) }}</td></tr>
    @endforeach
    @php($drinks = \App\Models\Product::query()->active()->whereNotNull('rental_price')->get())
    @if ($drinks->isNotEmpty())
        <tr><th colspan="2">Drank</th></tr>
        @foreach ($drinks as $product)
            <tr><td>{{ $product->name }}</td><td>{{ euro($product->rental_price) }}</td></tr>
        @endforeach
    @endif
    </tbody>
</table>
