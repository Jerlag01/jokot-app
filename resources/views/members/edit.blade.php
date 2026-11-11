@extends('layouts.site', ['title' => 'Mijn gegevens', 'noindex' => true])

@section('content')
    <section class="page-header">
        <div class="container">
            <p class="eyebrow">Lid {{ $member->member_number }}@if ($member->memberships->isNotEmpty()) &middot; lid in {{ $member->memberships->pluck('year')->sortDesc()->implode(', ') }}@endif</p>
            <h1>Hallo {{ $member->first_name }}!</h1>
        </div>
    </section>
    <section class="section">
        <div class="container narrow">
            @if ($member->card_number)
                <div class="card-balance" id="saldo">
                    <h2>Saldo op je lidkaart</h2>
                    <p class="card-balance-amount">{{ euro($balance) }}</p>
                    <p class="muted small">Kaart {{ $member->card_number }} &middot; te gebruiken aan de toog</p>
                </div>
                @if ($topUps->enabled())
                    @error('amount')<div class="alert alert-error" role="alert">{{ $message }}</div>@enderror
                    <form method="post" action="{{ route('members.topup', $member->token) }}" class="form">
                        @csrf
                        @include('card-topups.partials.amount-fields', ['legend' => 'Kaart opladen'])
                        <button type="submit" class="button">Opladen en betalen</button>
                    </form>
                @endif
                @if ($transactions->isNotEmpty())
                    <table class="table" style="margin: 1.5rem 0 2.5rem;">
                        <thead><tr><th>Datum</th><th>Omschrijving</th><th style="text-align:right">Bedrag</th></tr></thead>
                        <tbody>
                            @foreach ($transactions as $transaction)
                                <tr>
                                    <td>{{ ($transaction->completed_at ?? $transaction->created_at)?->format('d/m/Y H:i') }}</td>
                                    <td>{{ $transaction->description ?: $transaction->type?->getLabel() }}</td>
                                    <td style="text-align:right; white-space:nowrap;">{{ (float) $transaction->amount > 0 ? '+' : '' }}{{ euro($transaction->amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            @endif
            <h2>Mijn gegevens</h2>
            <p>Hier pas je je gegevens aan die we bijhouden in onze ledenlijst. We gebruiken ze enkel om je te contacteren over het jeugdhuis.</p>
            @if (session('saved'))
                <div class="alert alert-success" role="status"><strong>Bedankt, je gegevens zijn opgeslagen.</strong></div>
            @endif
            @if ($errors->hasAny(['first_name', 'last_name', 'street', 'house_number', 'box', 'postal_code', 'city', 'email', 'phone']))
                <div class="alert alert-error" role="alert"><ul>@foreach ($errors->getMessages() as $field => $messages)@continue($field === 'amount')@foreach ($messages as $error)<li>{{ $error }}</li>@endforeach@endforeach</ul></div>
            @endif
            <form method="post" action="{{ route('members.update', $member->token) }}" class="form">
                @csrf
                @method('PUT')
                <fieldset>
                    <legend>Mijn gegevens</legend>
                    <div class="form-grid">
                        <label class="field"><span>Voornaam *</span><input type="text" name="first_name" value="{{ old('first_name', $member->first_name) }}" required autocomplete="given-name"></label>
                        <label class="field"><span>Naam *</span><input type="text" name="last_name" value="{{ old('last_name', $member->last_name) }}" required autocomplete="family-name"></label>
                        <label class="field"><span>E-mail</span><input type="email" name="email" value="{{ old('email', $member->email) }}" autocomplete="email"></label>
                        <label class="field"><span>Telefoon</span><input type="tel" name="phone" value="{{ old('phone', $member->phone) }}" autocomplete="tel"></label>
                    </div>
                    <div class="form-grid form-grid-address">
                        <label class="field field-street"><span>Straat</span><input type="text" name="street" value="{{ old('street', $member->street) }}" autocomplete="address-line1"></label>
                        <label class="field"><span>Huisnummer</span><input type="text" name="house_number" value="{{ old('house_number', $member->house_number) }}"></label>
                        <label class="field"><span>Bus</span><input type="text" name="box" value="{{ old('box', $member->box) }}"></label>
                        <label class="field"><span>Postcode</span><input type="text" name="postal_code" value="{{ old('postal_code', $member->postal_code) }}" autocomplete="postal-code"></label>
                        <label class="field field-city"><span>Gemeente</span><input type="text" name="city" value="{{ old('city', $member->city) }}" autocomplete="address-level2"></label>
                    </div>
                </fieldset>
                <button type="submit" class="button button-large">Opslaan</button>
            </form>
            <p class="muted small" style="margin-top: 1.5rem;">Kaart verloren? Laat het weten aan het bestuur via <a href="mailto:{{ settings()->get('email') }}">{{ settings()->get('email') }}</a>, dan maken we een nieuwe kaart en werkt deze link niet meer.</p>
        </div>
    </section>
@endsection
