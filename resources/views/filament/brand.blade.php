{{-- Filament's precompiled CSS only ships its own classes, so sizing here uses inline styles. --}}
<span style="display: flex; align-items: center; gap: .7rem; height: 2.75rem; white-space: nowrap;">
    <img src="{{ asset('images/jokot-logo-zwart.png') }}" alt="" style="height: 2.5rem; width: 2.5rem; border-radius: 9999px; box-shadow: 0 0 0 2px rgba(255,255,255,.15);">
    <span style="display: flex; flex-direction: column; line-height: 1.1;">
        <span style="color: inherit; font-size: 1rem; font-weight: 700; letter-spacing: -.01em;">{{ settings()->siteName() }}</span>
        <span class="jk-mark-label" style="font-size: .625rem; font-weight: 600; letter-spacing: .25em; text-transform: uppercase;">Beheer</span>
    </span>
</span>
