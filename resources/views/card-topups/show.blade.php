@extends('layouts.site', ['title' => 'Lidkaart opladen', 'noindex' => true])

@section('content')
    <section class="page-header">
        <div class="container">
            <p class="eyebrow">Lidkaart {{ $topUp->member->card_number }}</p>
            <h1>Lidkaart opladen</h1>
        </div>
    </section>
    <section class="section">
        <div class="container narrow">
            @if ($topUp->isCompleted())
                <div class="alert alert-success" role="status">
                    <strong>Gelukt! Er staat {{ euro($topUp->amount) }} extra op de lidkaart van {{ $topUp->member->first_name }}.</strong>
                    @if ($topUp->email) We stuurden een bevestiging naar {{ $topUp->email }}.@endif
                </div>
            @elseif ($topUp->isPending())
                @if ($cancelledAtProvider)
                    <div class="alert">Je hebt de betaling afgebroken. Er werd niets opgeladen.</div>
                @else
                    <div class="alert" role="status">
                        <strong>We wachten nog op de bevestiging van je betaling.</strong>
                        Dit duurt meestal maar enkele seconden. <a href="{{ $topUp->publicUrl() }}">Vernieuw deze pagina</a>.
                    </div>
                @endif
                @if ($topUp->checkout_url && $topUp->created_at->gt(now()->subMinutes(30)))
                    <form method="post" action="{{ route('card-topups.pay', $topUp) }}">
                        @csrf
                        <button type="submit" class="button">Verder betalen</button>
                    </form>
                @endif
            @else
                <div class="alert alert-error">Deze betaling is niet gelukt. Er werd niets opgeladen.</div>
                <p><a class="button" href="{{ route('card-topups.create') }}">Opnieuw proberen</a></p>
            @endif
        </div>
    </section>
@endsection
