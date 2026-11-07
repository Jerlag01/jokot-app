@php
    [$earliest, $latest] = app(\App\Support\Availability::class)->bookingWindow();
    $rates = \App\Models\RentalRate::query()->active()->get();
    $extras = \App\Models\RentalExtra::query()->active()->get();
    $drinks = \App\Models\Product::query()->orderable()->get();
    $requestedDate = request('datum');
    $requestedDate = is_string($requestedDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate) ? $requestedDate : null;
@endphp
<section class="section" id="huuraanvraag">
    <div class="container narrow">
        @if (! empty($data['heading']))
            <h2>{{ $data['heading'] }}</h2>
        @endif
        @if (! empty($data['intro']))
            <p>{{ $data['intro'] }}</p>
        @endif

        @if (session('rental_request_reference'))
            <div class="alert alert-success" role="status">
                <strong>Bedankt! Je aanvraag ({{ session('rental_request_reference') }}) is goed ontvangen.</strong>
                Je krijgt een bevestiging per e-mail. Het bestuur neemt zo snel mogelijk contact met je op. Je reservatie is pas definitief na onze bevestiging.
            </div>
        @elseif (! settings()->get('rentals_open', true))
            <div class="alert">Huuraanvragen via de website zijn tijdelijk gesloten. Contacteer ons via <a href="mailto:{{ settings()->get('email') }}">{{ settings()->get('email') }}</a>.</div>
        @else
            @if ($errors->any())
                <div class="alert alert-error" role="alert">
                    <strong>Controleer het formulier:</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="post" action="{{ route('rental-requests.store') }}" class="form" novalidate>
                @csrf
                <div class="hp-field" aria-hidden="true">
                    <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                </div>

                <fieldset>
                    <legend>1. Je event</legend>
                    <div class="form-grid">
                        <label class="field">
                            <span>Datum *</span>
                            <input type="date" name="event_date" required data-event-date
                                   min="{{ $earliest->toDateString() }}" max="{{ $latest->toDateString() }}"
                                   value="{{ old('event_date', $requestedDate) }}" @error('event_date') aria-invalid="true" @enderror>
                            <small>Tussen {{ $earliest->translatedFormat('j F Y') }} en {{ $latest->translatedFormat('j F Y') }}. Bekijk eerst de kalender.</small>
                        </label>
                        <label class="field">
                            <span>Gebruikersgroep *</span>
                            <select name="rental_rate_id" required @error('rental_rate_id') aria-invalid="true" @enderror>
                                <option value="">Kies...</option>
                                @foreach ($rates as $rate)
                                    <option value="{{ $rate->id }}" @selected((string) old('rental_rate_id') === (string) $rate->id)>{{ $rate->name }} ({{ euro($rate->price) }})</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="field">
                            <span>Hoeveel volk verwacht u?</span>
                            <input type="number" name="expected_guests" min="1" max="350" value="{{ old('expected_guests') }}" @error('expected_guests') aria-invalid="true" @enderror>
                        </label>
                        <label class="field">
                            <span>DJ-tafel</span>
                            <select name="rental_extra_id">
                                <option value="">Nee</option>
                                @foreach ($extras as $extra)
                                    <option value="{{ $extra->id }}" @selected((string) old('rental_extra_id') === (string) $extra->id)>{{ $extra->name }} ({{ euro($extra->price) }})</option>
                                @endforeach
                            </select>
                        </label>
                        <div class="field field-full">
                            <span>Neemt u zelf iets mee van wijn, cava of champagne? *</span>
                            <div class="radio-row">
                                <label><input type="radio" name="brings_own_wine" value="0" @checked(old('brings_own_wine', '0') === '0')> Nee</label>
                                <label><input type="radio" name="brings_own_wine" value="1" @checked(old('brings_own_wine') === '1')> Ja</label>
                            </div>
                            <small>Kurkgeld: €3 per geopende fles van 75cl.</small>
                        </div>
                        <label class="field field-full">
                            <span>Details</span>
                            <textarea name="details" rows="4" placeholder="Wat voor event, uren, speciale wensen...">{{ old('details') }}</textarea>
                        </label>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>2. Je gegevens</legend>
                    <div class="form-grid">
                        @foreach ([
                            ['first_name', 'Voornaam *', 'text', 'given-name'],
                            ['last_name', 'Achternaam *', 'text', 'family-name'],
                            ['organisation', 'Organisatie', 'text', 'organization'],
                            ['company_number', 'Ondernemingsnummer', 'text', 'off'],
                            ['email', 'E-mail *', 'email', 'email'],
                            ['phone', 'Telefoon *', 'tel', 'tel'],
                        ] as [$name, $label, $type, $autocomplete])
                            <label class="field">
                                <span>{{ $label }}</span>
                                <input type="{{ $type }}" name="{{ $name }}" value="{{ old($name) }}" autocomplete="{{ $autocomplete }}" @error($name) aria-invalid="true" @enderror>
                            </label>
                        @endforeach
                    </div>
                    <div class="form-grid form-grid-address">
                        <label class="field field-street"><span>Straat *</span><input type="text" name="street" value="{{ old('street') }}" autocomplete="address-line1" @error('street') aria-invalid="true" @enderror></label>
                        <label class="field"><span>Huisnummer *</span><input type="text" name="house_number" value="{{ old('house_number') }}" @error('house_number') aria-invalid="true" @enderror></label>
                        <label class="field"><span>Bus</span><input type="text" name="box" value="{{ old('box') }}"></label>
                        <label class="field"><span>Postcode *</span><input type="text" name="postal_code" value="{{ old('postal_code') }}" autocomplete="postal-code" @error('postal_code') aria-invalid="true" @enderror></label>
                        <label class="field field-city"><span>Gemeente *</span><input type="text" name="city" value="{{ old('city') }}" autocomplete="address-level2" @error('city') aria-invalid="true" @enderror></label>
                    </div>
                </fieldset>

                @if ($drinks->isNotEmpty())
                    <fieldset>
                        <legend>3. Drank</legend>
                        <p class="muted small">Alle dranken worden afgenomen van het jeugdhuis. Geef een eerste inschatting; de afrekening gebeurt op basis van het werkelijke verbruik.</p>
                        <div class="drink-grid">
                            @foreach ($drinks as $drink)
                                <label class="drink-row">
                                    <span>{{ $drink->label() }}</span>
                                    <input type="number" name="drinks[{{ $drink->id }}]" min="0" max="999" inputmode="numeric" placeholder="0" value="{{ old('drinks.'.$drink->id) }}">
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                <label class="checkbox">
                    <input type="checkbox" name="terms" value="1" @checked(old('terms')) @error('terms') aria-invalid="true" @enderror>
                    <span>Ik heb de voorwaarden voor verhuur hierboven gelezen en ga ermee akkoord. *</span>
                </label>

                <button type="submit" class="button button-large">Aanvraag versturen</button>
            </form>
        @endif
    </div>
</section>
