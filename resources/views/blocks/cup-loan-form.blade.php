<section class="section" id="wisselbekers">
    <div class="container narrow">
        @if (! empty($data['heading']))
            <h2>{{ $data['heading'] }}</h2>
        @endif
        @if (session('cup_loan_reference'))
            <div class="alert alert-success" role="status">
                <strong>Bedankt! Je aanvraag ({{ session('cup_loan_reference') }}) is goed ontvangen.</strong>
                We bekijken of er genoeg bekers vrij zijn en sturen je de overeenkomst om online te ondertekenen.
            </div>
        @elseif (! settings()->get('cup_loans_open', true))
            <div class="alert">Aanvragen voor wisselbekers zijn tijdelijk gesloten. Contacteer ons via <a href="mailto:{{ settings()->get('email') }}">{{ settings()->get('email') }}</a>.</div>
        @else
            @if ($errors->any())
                <div class="alert alert-error" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form method="post" action="{{ route('cup-loans.store') }}" class="form" novalidate>
                @csrf
                <div class="hp-field" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                <fieldset>
                    <legend>1. Wat en wanneer</legend>
                    <div class="form-grid">
                        <label class="field"><span>Aantal bekers *</span><input type="number" name="cups" min="1" value="{{ old('cups') }}" required></label>
                        <label class="field"><span>Aantal bakken</span><input type="number" name="crates" min="0" value="{{ old('crates') }}"><small>Leeg laten als je het niet weet.</small></label>
                        <label class="field"><span>Ophalen op *</span><input type="date" name="starts_on" min="{{ today()->toDateString() }}" value="{{ old('starts_on') }}" required></label>
                        <label class="field"><span>Terugbrengen op *</span><input type="date" name="ends_on" min="{{ today()->toDateString() }}" value="{{ old('ends_on') }}" required></label>
                        <label class="field field-full"><span>Evenement</span><input type="text" name="event_name" value="{{ old('event_name') }}" placeholder="bv. schoolfeest, fuif, ..."></label>
                    </div>
                </fieldset>
                <fieldset>
                    <legend>2. Huurder</legend>
                    <div class="form-grid">
                        <label class="field"><span>Organisatie *</span><input type="text" name="organisation" value="{{ old('organisation') }}" required></label>
                        <label class="field"><span>Vertegenwoordigd door *</span><input type="text" name="representative" value="{{ old('representative') }}" required></label>
                        <label class="field"><span>E-mail *</span><input type="email" name="email" value="{{ old('email') }}" required></label>
                        <label class="field"><span>Telefoon *</span><input type="tel" name="phone" value="{{ old('phone') }}" required></label>
                        <label class="field"><span>Ondernemingsnummer</span><input type="text" name="vat_number" value="{{ old('vat_number') }}"></label>
                    </div>
                    <div class="form-grid form-grid-address">
                        <label class="field field-street"><span>Straat *</span><input type="text" name="street" value="{{ old('street') }}" required></label>
                        <label class="field"><span>Huisnummer *</span><input type="text" name="house_number" value="{{ old('house_number') }}" required></label>
                        <label class="field"><span>Bus</span><input type="text" name="box" value="{{ old('box') }}"></label>
                        <label class="field"><span>Postcode *</span><input type="text" name="postal_code" value="{{ old('postal_code') }}" required></label>
                        <label class="field field-city"><span>Gemeente *</span><input type="text" name="city" value="{{ old('city') }}" required></label>
                    </div>
                    <label class="field" style="margin-top: 1rem;"><span>Opmerkingen</span><textarea name="details" rows="3">{{ old('details') }}</textarea></label>
                </fieldset>
                <label class="checkbox">
                    <input type="checkbox" name="terms" value="1" @checked(old('terms'))>
                    <span>Ik ga akkoord met de voorwaarden hierboven (huurprijs {{ euro(settings()->get('cup_rental_fee')) }}, {{ euro(settings()->get('cup_fee')) }} per missende of vuile beker, {{ euro(settings()->get('cup_crate_fee')) }} per verloren of beschadigde bak). *</span>
                </label>
                <button type="submit" class="button button-large">Aanvraag versturen</button>
            </form>
        @endif
    </div>
</section>
