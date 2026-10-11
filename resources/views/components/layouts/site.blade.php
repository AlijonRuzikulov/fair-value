@props(['title' => null, 'description' => null, 'page' => null])
@php
    use App\Models\Setting;
    use App\Support\Format;
    $locales = config('fairvalue.locales');
    $locale = app()->getLocale();
    $brand = Setting::get('name');
    $phone = Setting::get('phone');
    $fullTitle = $title ? $title.' — '.$brand : $brand.' — '.__('Valuation company');
    $metaDescription = $description ?? __('Licensed valuation of real estate, vehicles, equipment and business, damage assessment, business plans and expert opinions. Reports are accepted by banks, notaries and courts.');
    $nav = [
        'services' => [__('Services'), route('services')],
        'about' => [__('About us'), route('about')],
        'verify' => [__('Check a report'), route('verify')],
        'contact' => [__('Contacts'), route('contact')],
    ];
    $instagram = Setting::get('instagram');
    $telegram = Setting::get('telegram');
@endphp
<!DOCTYPE html>
<html lang="{{ $locales[$locale]['html'] ?? $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>document.documentElement.classList.add('js')</script>
    <title>{{ $fullTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $brand }}">
    <meta property="og:title" content="{{ $fullTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('images/og-image.jpg') }}">
    <meta name="theme-color" content="#0e1a2b">
    <link rel="icon" href="{{ asset('images/favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ filemtime(public_path('css/site.css')) }}">
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'ProfessionalService',
        'name' => $brand,
        'legalName' => Setting::get('legal_name'),
        'url' => url('/'),
        'telephone' => $phone,
        'email' => Setting::get('email'),
        'address' => Setting::get('address') ?: null,
        'openingHours' => Setting::get('working_hours'),
        'areaServed' => 'UZ',
        'sameAs' => array_values(array_filter([$instagram ? 'https://instagram.com/'.$instagram : null, $telegram ? 'https://t.me/'.$telegram : null])),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
</head>
<body @class(['site', 'page-'.$page => $page])>
<a class="skip-link" href="#main">{{ __('Skip to content') }}</a>

<div class="topline">
    <div class="container topline-inner">
        <div class="topline-info">
            <a href="{{ Format::telLink($phone) }}"><x-icon name="phone" :size="14" /> {{ $phone }}</a>
            @if (Setting::get('working_hours'))<span class="hide-sm"><x-icon name="clock" :size="14" /> {{ __('Mon–Sat') }}, {{ Setting::get('working_hours') }}</span>@endif
            @if (Setting::get('address'))<span class="hide-md"><x-icon name="map-pin" :size="14" /> {{ Setting::get('address') }}</span>@endif
        </div>
        <div class="topline-side">
            @if ($telegram)<a href="https://t.me/{{ ltrim($telegram, '@') }}" target="_blank" rel="noopener" aria-label="Telegram"><x-icon name="send" :size="15" /></a>@endif
            @if ($instagram)<a href="https://instagram.com/{{ ltrim($instagram, '@') }}" target="_blank" rel="noopener" aria-label="Instagram"><x-icon name="instagram" :size="15" /></a>@endif
            <nav class="langs" aria-label="{{ __('Language') }}">
                @foreach ($locales as $code => $meta)
                    <a href="{{ route('locale', $code) }}" @class(['is-active' => $code === $locale]) hreflang="{{ $meta['html'] }}" lang="{{ $meta['html'] }}">{{ $meta['short'] }}</a>
                @endforeach
            </nav>
        </div>
    </div>
</div>

<header class="header" data-header>
    <div class="container header-inner">
        <a class="brand" href="{{ route('home') }}" aria-label="{{ $brand }}">
            <x-logo-mark :size="42" />
            <span>
                <strong>{{ $brand }}</strong>
                <small>{{ __('Valuation company') }}</small>
            </span>
        </a>
        <nav class="nav" id="site-nav" aria-label="{{ __('Main menu') }}">
            @foreach ($nav as $key => [$label, $url])
                <a href="{{ $url }}" @class(['is-active' => $page === $key]) @if ($page === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
            <a class="btn btn-primary nav-cta" href="{{ route('home') }}#request">{{ __('Leave a request') }}</a>
        </nav>
        <a class="header-phone" href="{{ Format::telLink($phone) }}"><x-icon name="phone" :size="18" /><span>{{ $phone }}</span></a>
        <a class="btn btn-primary header-cta" href="{{ route('home') }}#request">{{ __('Leave a request') }}</a>
        <button class="menu-btn" type="button" data-menu aria-controls="site-nav" aria-expanded="false" aria-label="{{ __('Menu') }}"><x-icon name="menu" :size="24" /></button>
    </div>
</header>

<main id="main">
    {{ $slot }}
</main>

<footer class="footer">
    <div class="container footer-grid">
        <div class="footer-about">
            <a class="brand brand-light" href="{{ route('home') }}">
                <x-logo-mark :size="42" />
                <span><strong>{{ $brand }}</strong><small>{{ __('Valuation company') }}</small></span>
            </a>
            <p>{{ __('Independent valuation of all types of property, business plans, expert opinions and audit. We work all over Uzbekistan.') }}</p>
            @if (Setting::get('license'))<p class="footer-license"><x-icon name="shield-check" :size="16" /> {{ Setting::get('license') }}</p>@endif
        </div>
        <div>
            <h3>{{ __('Services') }}</h3>
            <ul>
                @foreach (\App\Support\Services::titles() as $slug => $label)
                    <li><a href="{{ route('services.show', $slug) }}">{{ $label }}</a></li>
                @endforeach
            </ul>
        </div>
        <div>
            <h3>{{ __('Company') }}</h3>
            <ul>
                @foreach ($nav as [$label, $url])
                    <li><a href="{{ $url }}">{{ $label }}</a></li>
                @endforeach
            </ul>
        </div>
        <div>
            <h3>{{ __('Contacts') }}</h3>
            <ul class="footer-contacts">
                <li><x-icon name="phone" :size="16" /><a href="{{ Format::telLink($phone) }}">{{ $phone }}</a></li>
                @if (Setting::get('phone2'))<li><x-icon name="phone" :size="16" /><a href="{{ Format::telLink(Setting::get('phone2')) }}">{{ Setting::get('phone2') }}</a></li>@endif
                @if (Setting::get('email'))<li><x-icon name="mail" :size="16" /><a href="mailto:{{ Setting::get('email') }}">{{ Setting::get('email') }}</a></li>@endif
                @if (Setting::get('address'))<li><x-icon name="map-pin" :size="16" /><span>{{ Setting::get('address') }}</span></li>@endif
                @if (Setting::get('working_hours'))<li><x-icon name="clock" :size="16" /><span>{{ __('Mon–Sat') }}, {{ Setting::get('working_hours') }}</span></li>@endif
            </ul>
            <div class="socials">
                @if ($telegram)<a href="https://t.me/{{ ltrim($telegram, '@') }}" target="_blank" rel="noopener" aria-label="Telegram"><x-icon name="send" :size="18" /></a>@endif
                @if ($instagram)<a href="https://instagram.com/{{ ltrim($instagram, '@') }}" target="_blank" rel="noopener" aria-label="Instagram"><x-icon name="instagram" :size="18" /></a>@endif
            </div>
        </div>
    </div>
    <div class="container footer-bottom">
        <span>© {{ date('Y') }} {{ Setting::get('legal_name') }}. {{ __('All rights reserved.') }}</span>
        <a href="{{ route('login') }}"><x-icon name="lock" :size="14" /> {{ __('Staff sign in') }}</a>
    </div>
</footer>

<a class="call-fab" href="{{ Format::telLink($phone) }}" aria-label="{{ __('Call') }}"><x-icon name="phone" :size="22" /></a>

<script src="{{ asset('js/site.js') }}?v={{ filemtime(public_path('js/site.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
