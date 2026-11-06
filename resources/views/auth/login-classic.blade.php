{{-- The sign-in page of the classic look: the logo, the e-mail and password boxes with their icons, a wide button and the support link. --}}
@php $locales = config('fairvalue.locales'); $name = \App\Models\Setting::get('name'); @endphp
<!DOCTYPE html>
<html lang="{{ $locales[app()->getLocale()]['html'] ?? app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('Sign in') }} — {{ $name }}</title>
    <link rel="icon" href="{{ asset('images/favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/roboto.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/admin-classic.css') }}?v={{ filemtime(public_path('css/admin-classic.css')) }}">
</head>
<body class="theme-classic simple-login-page">
<main class="simple-login">
    <a class="simple-login-logo" href="{{ route('home') }}">
        <x-logo-mark :size="76" />
        <span>{{ $name }}<small>{{ __('Valuation system') }}</small></span>
    </a>

    <form method="post" action="{{ url('/login') }}" class="simple-login-form">
        @csrf
        @if ($errors->any())
            <p class="simple-login-error" role="alert">{{ $errors->first() }}</p>
        @endif
        <label class="login-box">
            <span class="login-box-icon"><x-icon name="user" :size="16" /></span>
            <input id="f-email" name="email" type="email" value="{{ old('email') }}" placeholder="{{ __('E-mail') }}" required autofocus autocomplete="username" aria-label="{{ __('E-mail') }}">
        </label>
        <label class="login-box">
            <span class="login-box-icon"><x-icon name="lock" :size="16" /></span>
            <input id="f-password" name="password" type="password" placeholder="{{ __('Password') }}" required autocomplete="current-password" aria-label="{{ __('Password') }}">
        </label>
        <button class="simple-login-button" type="submit">{{ __('Sign in') }}</button>

        @if ($hint)
            <div class="auth-demo">
                <span><strong>{{ __('First sign-in') }}:</strong> <code>{{ $hint['email'] }}</code> / <code>{{ $hint['password'] }}</code></span>
                <button type="button" class="btn btn-sm" data-demo-fill data-email="{{ $hint['email'] }}" data-password="{{ $hint['password'] }}">{{ __('Fill in') }}</button>
            </div>
        @endif
    </form>

    <p class="simple-login-foot">
        {{ $name }} · <a href="{{ route('contact') }}">{{ __('Get support') }}</a>
    </p>
</main>
@if ($hint)
    <script nonce="{{ Vite::cspNonce() }}">
        document.querySelector('[data-demo-fill]').addEventListener('click', function () {
            document.getElementById('f-email').value = this.dataset.email;
            document.getElementById('f-password').value = this.dataset.password;
        });
    </script>
@endif
</body>
</html>
