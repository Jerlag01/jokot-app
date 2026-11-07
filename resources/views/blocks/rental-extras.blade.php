@php($extras = \App\Models\RentalExtra::query()->active()->get())
<section class="section section-tight">
    <div class="container narrow">
        @if (! empty($data['heading']))
            <h2>{{ $data['heading'] }}</h2>
        @endif
        @if (! empty($data['intro']))
            <p>{{ $data['intro'] }}</p>
        @endif
        <table class="table">
            <thead><tr><th>Materialen &amp; diensten</th><th class="num">Huurprijs</th><th class="num">Waarborg</th></tr></thead>
            <tbody>
            @foreach ($extras as $extra)
                <tr>
                    <td>{{ $extra->name }}@if ($extra->description)<br><span class="muted small">{{ $extra->description }}</span>@endif</td>
                    <td class="num">{{ euro($extra->price) }}</td>
                    <td class="num">{{ euro($extra->deposit) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
