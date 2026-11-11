@extends('layouts.site', ['title' => 'Lidkaart opladen', 'description' => 'Zet online budget op je lidkaart van '.settings()->siteName().' en betaal er makkelijk mee aan de toog.'])

@section('content')
    <section class="page-header">
        <div class="container">
            <p class="eyebrow">Leden</p>
            <h1>Lidkaart opladen</h1>
        </div>
    </section>
    <section class="section">
        <div class="container narrow">
            @if (! $topUps->enabled())
                <div class="alert">Online opladen is momenteel niet mogelijk. Je kan je kaart opladen aan de toog.</div>
            @else
                <p>Zet geld op je lidkaart en betaal ermee aan de toog. Je vindt het kaartnummer onder de barcode op je kaart.</p>
                @if ($errors->any())
                    <div class="alert alert-error" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif
                <form method="post" action="{{ route('card-topups.store') }}" class="form">
                    @csrf
                    <fieldset>
                        <legend>Je lidkaart</legend>
                        <div class="form-grid">
                            <label class="field"><span>Kaartnummer *</span><input type="text" name="card_number" value="{{ old('card_number') }}" required inputmode="numeric" pattern="\d{5}" maxlength="5" placeholder="5 cijfers" autocomplete="off"></label>
                            <label class="field"><span>Familienaam van het lid *</span><input type="text" name="last_name" value="{{ old('last_name') }}" required autocomplete="family-name"></label>
                            <label class="field"><span>E-mail voor de bevestiging</span><input type="email" name="email" value="{{ old('email') }}" autocomplete="email"></label>
                        </div>
                    </fieldset>
                    @include('card-topups.partials.amount-fields')
                    <button type="submit" class="button button-large">Opladen en betalen</button>
                </form>
                <p class="muted small" style="margin-top: 1.5rem;">Je saldo en je overzicht vind je via de QR-code op je lidkaart. Nog geen lidkaart? Vraag er een aan de toog.</p>
            @endif
        </div>
    </section>
@endsection
