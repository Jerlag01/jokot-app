@php($providers = app(\App\Support\Payments\Payments::class)->available())
<fieldset>
    <legend>{{ $legend ?? 'Bedrag' }}</legend>
    <div class="radio-row">
        @foreach ($topUps->presets() as $preset)
            <label><input type="radio" name="amount" value="{{ $preset }}" @checked((string) old('amount', $topUps->presets()[1] ?? $topUps->presets()[0] ?? '') === (string) $preset)> {{ euro($preset) }}</label>
        @endforeach
    </div>
    <label class="field" style="margin-top: 1rem; max-width: 14rem;">
        <span>Ander bedrag (€ {{ (int) $topUps->minimum() }} tot {{ (int) $topUps->maximum() }})</span>
        <input type="number" name="amount" min="{{ $topUps->minimum() }}" max="{{ $topUps->maximum() }}" step="1" inputmode="numeric"
            value="{{ in_array((int) old('amount'), $topUps->presets(), true) ? '' : old('amount') }}"
            oninput="if (this.value) this.form.querySelectorAll('input[type=radio][name=amount]').forEach(r => r.checked = false)">
    </label>
    @if ($topUps->maximumBalance() !== null)
        <p class="muted small">Er kan maximaal {{ euro($topUps->maximumBalance()) }} op een kaart staan.</p>
    @endif
</fieldset>
@if (count($providers) > 1)
    <fieldset>
        <legend>Betalen met</legend>
        <div class="radio-row">
            @foreach ($providers as $provider)
                <label><input type="radio" name="provider" value="{{ $provider->value }}" @checked(old('provider', $providers[0]->value) === $provider->value)> {{ $provider === \App\Enums\PaymentProvider::Stripe ? 'Bancontact / kaart (Stripe)' : 'Bancontact / kaart (SumUp)' }}</label>
            @endforeach
        </div>
    </fieldset>
@endif
