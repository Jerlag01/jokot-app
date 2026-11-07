@php($hours = \App\Models\OpeningHour::query()->orderBy('weekday')->get())
<section class="section section-tight">
    <div class="container narrow">
        @if (! empty($data['heading']))
            <h2>{{ $data['heading'] }}</h2>
        @endif
        <table class="table table-hours">
            <thead><tr><th>Dag</th><th>Van</th><th>Tot</th></tr></thead>
            <tbody>
            @foreach ($hours as $hour)
                <tr @class(['is-today' => $hour->weekday === now()->dayOfWeekIso])>
                    <td>{{ $hour->dayName() }}@if ($hour->note)<sup>**</sup>@endif</td>
                    @if ($hour->is_closed)
                        <td>Gesloten</td><td>/</td>
                    @else
                        <td>{{ $hour->opens_at }}</td><td>{{ $hour->closes_at }}</td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>
        @if (settings()->get('opening_hours_note'))
            <p class="muted small">** {{ settings()->get('opening_hours_note') }}</p>
        @endif
    </div>
</section>
