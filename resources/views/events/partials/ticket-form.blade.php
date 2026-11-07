@php
    $types = $event->ticketTypes()->where('is_active', true)->get()->each->setRelation('event', $event);
    $providers = app(\App\Support\Payments\Payments::class)->available();
    $eventRemaining = $event->remainingCapacity();
    $onSale = $event->sellsTickets() && settings()->get('tickets_open', true);
    $needsPayment = $types->contains(fn ($type) => (float) $type->price > 0);
@endphp
<section class="section" id="tickets">
    <div class="container narrow">
        <h2>Tickets</h2>
        @if ($event->ticket_info)
            <p>{{ $event->ticket_info }}</p>
        @endif

        @if (! $onSale)
            <div class="alert">De online ticketverkoop is {{ $event->starts_at->isPast() ? 'afgelopen' : 'momenteel gesloten' }}.@if ($event->starts_at->isFuture()) Misschien zijn er nog tickets aan de deur.@endif</div>
        @elseif ($eventRemaining === 0)
            <div class="alert alert-error"><strong>Uitverkocht!</strong></div>
        @elseif ($needsPayment && $providers === [])
            <div class="alert">Online betalen is nog niet beschikbaar. Tickets zijn te koop aan de deur.</div>
        @else
            @if ($errors->any())
                <div class="alert alert-error" role="alert">
                    <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            <form method="post" action="{{ route('orders.store', $event) }}" class="form" novalidate>
                @csrf
                <div class="hp-field" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                <fieldset>
                    <legend>1. Kies je tickets</legend>
                    <div class="ticket-types">
                        @foreach ($types as $type)
                            @php($remaining = $type->remaining())
                            <div class="ticket-type @if (! $type->isOnSale() || $remaining === 0) is-unavailable @endif">
                                <div>
                                    <strong>{{ $type->name }}</strong>
                                    @if ($type->description)<span class="muted small">{{ $type->description }}</span>@endif
                                    @if (! $type->isOnSale())
                                        <span class="muted small">{{ $type->sales_start?->isFuture() ? 'Verkoop start '.$type->sales_start->translatedFormat('j F \o\m H:i') : 'Niet meer te koop' }}</span>
                                    @elseif ($remaining === 0)
                                        <span class="small" style="color: var(--primary);">Uitverkocht</span>
                                    @elseif ($remaining !== null && $remaining <= 20)
                                        <span class="small" style="color: var(--secondary);">Nog {{ $remaining }} beschikbaar</span>
                                    @endif
                                </div>
                                <span class="ticket-price">{{ (float) $type->price > 0 ? euro($type->price) : 'Gratis' }}</span>
                                @if ($type->isOnSale() && $remaining !== 0)
                                    <select name="tickets[{{ $type->id }}]" aria-label="Aantal {{ $type->name }}" data-ticket-price="{{ (float) $type->price }}">
                                        @for ($i = 0; $i <= min($type->max_per_order, $remaining ?? $type->max_per_order, $eventRemaining ?? $type->max_per_order); $i++)
                                            <option value="{{ $i }}" @selected((int) old('tickets.'.$type->id) === $i)>{{ $i }}</option>
                                        @endfor
                                    </select>
                                @else
                                    <span></span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <p class="ticket-total">Totaal: <strong data-ticket-total>€0</strong></p>
                </fieldset>
                <fieldset>
                    <legend>2. Je gegevens</legend>
                    <div class="form-grid">
                        <label class="field"><span>Naam *</span><input type="text" name="name" value="{{ old('name') }}" autocomplete="name" required></label>
                        <label class="field"><span>E-mail *</span><input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required><small>Hier sturen we je tickets naartoe.</small></label>
                        <label class="field"><span>Telefoon</span><input type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel"></label>
                    </div>
                </fieldset>
                @if ($needsPayment && count($providers) > 1)
                    <fieldset>
                        <legend>3. Betalen met</legend>
                        <div class="radio-row">
                            @foreach ($providers as $provider)
                                <label><input type="radio" name="provider" value="{{ $provider->value }}" @checked(old('provider', $providers[0]->value) === $provider->value)> {{ $provider === \App\Enums\PaymentProvider::Stripe ? 'Bancontact / kaart (Stripe)' : 'Bancontact / kaart (SumUp)' }}</label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif
                <label class="checkbox">
                    <input type="checkbox" name="terms" value="1" @checked(old('terms'))>
                    <span>Ik ga akkoord met de <a href="{{ url('/algemene-voorwaarden') }}" target="_blank">algemene voorwaarden</a>. *</span>
                </label>
                <button type="submit" class="button button-large">{{ $needsPayment ? 'Bestellen en betalen' : 'Tickets reserveren' }}</button>
                <p class="muted small" style="margin-top: 1rem;">Je tickets worden {{ (int) settings()->get('payments.order_minutes', 30) }} minuten voor je vastgehouden terwijl je betaalt.</p>
            </form>
        @endif
    </div>
</section>
