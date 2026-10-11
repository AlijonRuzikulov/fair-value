@php $locales = config('fairvalue.locales'); @endphp
<!DOCTYPE html>
<html lang="{{ $locales[app()->getLocale()]['html'] ?? app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('Sign in') }} — {{ \App\Models\Setting::get('name') }}</title>
    <link rel="icon" href="{{ asset('images/favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
</head>
<body>
<div class="auth">
    <section class="auth-side">
        <a class="auth-brand" href="{{ route('home') }}"><x-logo-mark :size="40" /> {{ \App\Models\Setting::get('name') }}</a>
        <div>
            <h2>{{ __('Valuation system') }}</h2>
            <p>{{ __('Clients, objects, reports and damage calculations of the company in one place.') }}</p>
            <div class="auth-points">
                <span><x-icon name="circle-check" :size="20" /> {{ __('Real estate, vehicles, equipment and business') }}</span>
                <span><x-icon name="circle-check" :size="20" /> {{ __('Damage calculation with price lists and repair norms') }}</span>
                <span><x-icon name="circle-check" :size="20" /> {{ __('QR verification of reports for banks and courts') }}</span>
            </div>
        </div>
        <p style="opacity: .75; font-size: 13px">© {{ date('Y') }} {{ \App\Models\Setting::get('legal_name') }}</p>
    </section>

    <main class="auth-main">
        <form method="post" action="{{ url('/login') }}" class="auth-form">
            @csrf
            <x-logo-mark :size="48" />
            <h1>{{ __('Sign in') }}</h1>
            <p class="lead">{{ __('Enter the e-mail and password issued by the administrator.') }}</p>

            <x-field name="email" type="email" :label="__('E-mail')" span="12" required autofocus autocomplete="username" />
            <x-field name="password" type="password" :label="__('Password')" span="12" required autocomplete="current-password" />

            <label class="check-inline"><input type="checkbox" name="remember" value="1"> {{ __('Remember me') }}</label>

            <button class="btn btn-primary" type="submit">{{ __('Sign in') }} <x-icon name="arrow-right" :size="16" /></button>

            <div class="auth-langs">
                @foreach ($locales as $code => $meta)
                    <a href="{{ route('locale', $code) }}" @class(['is-active' => $code === app()->getLocale()])>{{ $meta['label'] }}</a>
                @endforeach
            </div>
            <a class="back-link" style="justify-content: center" href="{{ route('home') }}"><x-icon name="arrow-left" :size="16" /> {{ __('Back to the website') }}</a>
        </form>
    </main>
</div>
</body>
</html>
