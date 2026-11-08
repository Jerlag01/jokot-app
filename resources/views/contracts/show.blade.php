@extends('layouts.site', ['title' => $contract->title(), 'noindex' => true])

@section('content')
    <section class="page-header">
        <div class="container">
            <p class="eyebrow">Referentie {{ $contract->contractable->reference }}</p>
            <h1>{{ $contract->title() }}</h1>
        </div>
    </section>
    <section class="section">
        <div class="container narrow">
            @if ($contract->isSigned())
                <div class="alert alert-success" role="status">
                    <strong>@if (session('signed'))Bedankt, het contract is ondertekend!@else Dit contract is ondertekend.@endif</strong>
                    Ondertekend door {{ $contract->signer_name }} op {{ $contract->signed_at->format('d/m/Y \o\m H:i') }}. Je krijgt een kopie per e-mail.
                </div>
            @endif
            <p><a class="button" href="{{ route('contracts.pdf', $contract->token) }}">Download als PDF</a></p>

            <div class="contract-body prose">{!! $body !!}</div>

            @unless ($contract->isSigned())
                <form method="post" action="{{ route('contracts.sign', $contract->token) }}" class="form" id="ondertekenen" data-signature-form>
                    @csrf
                    <fieldset>
                        <legend>Ondertekenen</legend>
                        @if ($errors->any())
                            <div class="alert alert-error" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                        @endif
                        <label class="field">
                            <span>Naam *</span>
                            <input type="text" name="signer_name" value="{{ old('signer_name', $contract->contractable->contractSignerName()) }}" required>
                        </label>
                        <div class="field" style="margin-top: 1rem;">
                            <span>Handtekening *</span>
                            <canvas class="signature-pad" width="600" height="200" data-signature-pad aria-label="Teken hier je handtekening"></canvas>
                            <small><button type="button" class="link-button" data-signature-clear>Wissen</button> &middot; Teken met je muis of vinger.</small>
                            <input type="hidden" name="signature" data-signature-input>
                        </div>
                        <label class="checkbox" style="margin-top: 1rem;">
                            <input type="checkbox" name="agree" value="1">
                            <span>Ik heb de overeenkomst gelezen en ga ermee akkoord.</span>
                        </label>
                        <button type="submit" class="button button-large">Ondertekenen</button>
                    </fieldset>
                </form>
            @endunless
        </div>
    </section>
@endsection
