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
        <span class="auth-glow g1"></span><span class="auth-glow g2"></span><span class="auth-glow g3"></span><span class="auth-glow g4"></span>
        <a class="auth-brand" href="{{ route('home') }}"><x-logo-mark :size="42" /> {{ \App\Models\Setting::get('name') }}</a>
        <div>
            <h2>{{ __('Valuation system') }} <span>{{ \App\Models\Setting::get('name') }}</span></h2>
            <p>{{ __('Clients, objects, reports and damage calculations of the company in one place.') }}</p>
            <div class="auth-points">
                <div class="auth-point"><i style="--g: linear-gradient(135deg, #34d399, #0d9488)"><x-icon name="building-2" :size="18" /></i> {{ __('Real estate, vehicles, equipment and business') }}</div>
                <div class="auth-point"><i style="--g: linear-gradient(135deg, #fbbf24, #f97316)"><x-icon name="calculator" :size="18" /></i> {{ __('Damage calculation with price lists and repair norms') }}</div>
                <div class="auth-point"><i style="--g: linear-gradient(135deg, #a78bfa, #c026d3)"><x-icon name="qr-code" :size="18" /></i> {{ __('QR verification of reports for banks and courts') }}</div>
            </div>
        </div>
        <p class="auth-copy">© {{ date('Y') }} {{ \App\Models\Setting::get('legal_name') }}</p>
    </section>

    <main class="auth-main">
        <div class="auth-card">
            <form method="post" action="{{ url('/login') }}" class="auth-form">
                @csrf
                <div class="auth-head">
                    <x-logo-mark :size="48" />
                    <div>
                        <h1>{{ __('Sign in') }}</h1>
                        <p>{{ __('Enter the e-mail and password issued by the administrator.') }}</p>
                    </div>
                </div>

                @if ($hint)
                    <div class="auth-demo">
                        <span><strong>{{ __('First sign-in') }}:</strong> <code>{{ $hint['email'] }}</code> / <code>{{ $hint['password'] }}</code></span>
                        <button type="button" class="btn btn-sm" data-demo-fill data-email="{{ $hint['email'] }}" data-password="{{ $hint['password'] }}"><x-icon name="key-round" :size="14" /> {{ __('Fill in') }}</button>
                        <small>{{ __('Change the password in your profile after signing in — this hint will then disappear.') }}</small>
                    </div>
                @endif

                <x-field name="email" type="email" :label="__('E-mail')" span="12" required autofocus autocomplete="username" />
                <x-field name="password" type="password" :label="__('Password')" span="12" required autocomplete="current-password" />

                <div class="auth-row">
                    <label class="check-inline"><input type="checkbox" name="remember" value="1"> {{ __('Remember me') }}</label>
                </div>

                <button class="btn btn-primary" type="submit">{{ __('Sign in') }} <x-icon name="arrow-right" :size="16" /></button>
            </form>

            <div class="auth-foot">
                <a class="back-link" href="{{ route('home') }}"><x-icon name="arrow-left" :size="16" /> {{ __('Back to the website') }}</a>
                <nav class="auth-langs" aria-label="{{ __('Language') }}">
                    @foreach ($locales as $code => $meta)
                        <a href="{{ route('locale', $code) }}" @class(['is-active' => $code === app()->getLocale()])>{{ $meta['short'] }}</a>
                    @endforeach
                </nav>
            </div>
        </div>
    </main>
</div>
@if ($hint)
    <script>
        document.querySelector('[data-demo-fill]').addEventListener('click', function () {
            document.getElementById('f-email').value = this.dataset.email;
            document.getElementById('f-password').value = this.dataset.password;
        });
    </script>
@endif
</body>
</html>
