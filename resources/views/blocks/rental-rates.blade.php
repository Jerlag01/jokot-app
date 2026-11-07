@php($rates = \App\Models\RentalRate::query()->active()->get())
<section class="section section-tight">
    <div class="container narrow">
        @if (! empty($data['heading']))
            <h2>{{ $data['heading'] }}</h2>
        @endif
        <table class="table">
            <thead><tr><th>Gebruikersgroepen</th><th class="num">Huurprijs</th><th class="num">Waarborg</th></tr></thead>
            <tbody>
            @foreach ($rates as $rate)
                <tr><td>{{ $rate->name }}</td><td class="num">{{ euro($rate->price) }}</td><td class="num">{{ euro($rate->deposit) }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
