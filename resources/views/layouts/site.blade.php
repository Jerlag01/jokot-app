<!DOCTYPE html>
<html lang="nl-BE">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) && $title !== settings()->siteName() ? $title.' - ' : '' }}{{ settings()->siteName() }}</title>
    @if (! empty($description))
        <meta name="description" content="{{ $description }}">
    @endif
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=poppins:400,500,600,700,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ filemtime(public_path('css/site.css')) }}">
</head>
<body>
<a class="skip-link" href="#inhoud">Spring naar de inhoud</a>

<header class="site-header" data-header>
    <div class="container header-inner">
        <a href="{{ route('home') }}" class="brand">
            <img src="{{ asset('images/jokot-logo-zwart.png') }}" alt="" width="48" height="48">
            <span>{{ settings()->siteName() }}</span>
        </a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="hoofdmenu" data-menu-toggle>
            <span class="sr-only">Menu</span>
            <span class="menu-toggle-bar"></span>
        </button>
        <nav id="hoofdmenu" class="main-nav" aria-label="Hoofdmenu">
            @foreach ($menuPages as $menuPage)
                <a href="{{ $menuPage->url() }}" @class(['is-active' => request()->url() === $menuPage->url()])>{{ $menuPage->title }}</a>
            @endforeach
        </nav>
    </div>
</header>

<main id="inhoud">
    @yield('content')
</main>

<footer class="site-footer">
    <div class="container footer-inner">
        <img class="footer-logo" src="{{ settings()->logoUrl() }}" alt="{{ settings()->siteName() }}" width="120" height="120" loading="lazy">
        <div class="footer-contact">
            <strong>{{ settings()->siteName() }}</strong>
            <span>{{ settings()->get('address') }}</span>
            <a href="mailto:{{ settings()->get('email') }}">{{ settings()->get('email') }}</a>
        </div>
        <div class="footer-social">
            @if (settings()->get('facebook_url'))
                <a href="{{ settings()->get('facebook_url') }}" target="_blank" rel="noopener" aria-label="Facebook">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M13.5 21v-7.5H16l.4-3h-2.9V8.6c0-.9.3-1.5 1.5-1.5h1.6V4.4c-.3 0-1.2-.1-2.3-.1-2.3 0-3.8 1.4-3.8 3.9v2.3H8v3h2.5V21h3z"/></svg>
                    <span>Facebook</span>
                </a>
            @endif
            @if (settings()->get('instagram_url'))
                <a href="{{ settings()->get('instagram_url') }}" target="_blank" rel="noopener" aria-label="Instagram">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/></svg>
                    <span>Instagram</span>
                </a>
            @endif
        </div>
    </div>
    <div class="container footer-bottom">
        <span>&copy; {{ now()->year }} {{ settings()->get('legal_name') }}. Alle rechten voorbehouden.</span>
        <a href="{{ url('/algemene-voorwaarden') }}">Algemene voorwaarden</a>
    </div>
</footer>

<a href="#inhoud" class="to-top" aria-label="Naar boven" data-to-top>
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m6 15 6-6 6 6"/></svg>
</a>
<script src="{{ asset('js/site.js') }}?v={{ filemtime(public_path('js/site.js')) }}" defer></script>
</body>
</html>
