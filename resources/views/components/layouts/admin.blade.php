@props(['title', 'nav' => null, 'icon' => 'layout-dashboard', 'subtitle' => null, 'actions' => null, 'back' => null])
@php
    $locales = config('fairvalue.locales');
    $locale = app()->getLocale();
    $user = auth()->user();
    $usd = $currencyRates->get('USD');
@endphp
<!DOCTYPE html>
<html lang="{{ $locales[$locale]['html'] ?? $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="districts-url" content="{{ route('admin.districts') }}">
    <title>{{ $title }} — {{ \App\Models\Setting::get('name') }}</title>
    <link rel="icon" href="{{ asset('images/favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
</head>
<body class="admin">
<div class="shell" data-shell>
    <aside class="sidebar" id="sidebar" aria-label="{{ __('Main menu') }}">
        <a class="sidebar-brand" href="{{ route('admin.dashboard') }}">
            <x-logo-mark :size="38" />
            <span>
                <strong>{{ \App\Models\Setting::get('name') }}</strong>
                <small>{{ __('Valuation system') }}</small>
            </span>
        </a>

        <nav class="nav">
            @foreach (\App\Support\AdminMenu::items($newLeadsCount) as $item)
                @if (isset($item['section']))
                    <p class="nav-section">{{ $item['section'] }}</p>
                @elseif (isset($item['children']))
                    @php $open = \App\Support\AdminMenu::isActive($item['key'], $nav); @endphp
                    <details class="nav-group" @if ($open) open @endif>
                        <summary @class(['nav-link', 'is-current' => $open])>
                            <x-icon :name="$item['icon']" :size="20" />
                            <span>{{ $item['label'] }}</span>
                            <x-icon name="chevron-down" :size="16" class="nav-caret" />
                        </summary>
                        <ul class="nav-sub">
                            @foreach ($item['children'] as $child)
                                <li><a href="{{ $child['url'] }}" @class(['nav-sublink', 'is-active' => $nav === $child['key']]) @if ($nav === $child['key']) aria-current="page" @endif>{{ $child['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </details>
                @else
                    <a href="{{ $item['url'] }}" @class(['nav-link', 'is-active' => \App\Support\AdminMenu::isActive($item['key'], $nav)]) @if ($nav === $item['key']) aria-current="page" @endif>
                        <x-icon :name="$item['icon']" :size="20" />
                        <span>{{ $item['label'] }}</span>
                        @if (! empty($item['badge']))<span class="nav-badge">{{ $item['badge'] }}</span>@endif
                    </a>
                @endif
            @endforeach
        </nav>

        <a class="sidebar-site" href="{{ route('home') }}" target="_blank" rel="noopener">
            <x-icon name="globe" :size="18" /> <span>{{ __('Open website') }}</span> <x-icon name="arrow-up-right" :size="14" />
        </a>
    </aside>
    <div class="sidebar-backdrop" data-sidebar-close></div>

    <div class="main">
        <header class="topbar">
            <button class="icon-btn menu-toggle" type="button" data-sidebar-toggle aria-controls="sidebar" aria-label="{{ __('Menu') }}"><x-icon name="menu" :size="22" /></button>

            <div class="page-heading">
                <span class="page-icon"><x-icon :name="$icon" :size="20" /></span>
                <div>
                    <h1>{{ $title }}</h1>
                    @if ($subtitle)<p>{{ $subtitle }}</p>@endif
                </div>
            </div>

            <div class="topbar-tools">
                @if ($usd)
                    <details class="dropdown currency">
                        <summary class="tool-btn" title="{{ __('Central Bank rate on :date', ['date' => $usd->rate_date->format('d.m.Y')]) }}">
                            <span class="hide-sm">1 USD =</span> <strong>{{ \App\Support\Format::number($usd->rate, 2) }}</strong> <span class="hide-sm">{{ __('sum') }}</span>
                            <x-icon name="chevron-down" :size="14" />
                        </summary>
                        <div class="dropdown-menu dropdown-right rates">
                            <p class="dropdown-title">{{ __('Central Bank rates') }} · {{ $usd->rate_date->format('d.m.Y') }}</p>
                            @foreach ($currencyRates as $rate)
                                <div class="rate-row">
                                    <span class="rate-code">{{ $rate->nominal > 1 ? $rate->nominal.' ' : '1 ' }}{{ $rate->code }}</span>
                                    <span class="rate-value">{{ \App\Support\Format::number($rate->rate, 2) }}</span>
                                    <span @class(['rate-diff', 'up' => $rate->diff > 0, 'down' => $rate->diff < 0])>
                                        @if ($rate->diff != 0)<x-icon :name="$rate->diff > 0 ? 'trending-up' : 'trending-down'" :size="14" />{{ \App\Support\Format::number(abs($rate->diff), 2) }}@endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endif

                <details class="dropdown">
                    <summary class="tool-btn" aria-label="{{ __('Language') }}"><x-icon name="globe" :size="18" /> <span class="hide-sm">{{ $locales[$locale]['label'] }}</span><span class="show-sm">{{ $locales[$locale]['short'] }}</span> <x-icon name="chevron-down" :size="14" /></summary>
                    <div class="dropdown-menu dropdown-right">
                        @foreach ($locales as $code => $meta)
                            <a href="{{ route('locale', $code) }}" @class(['dropdown-item', 'is-active' => $code === $locale])>{{ $meta['label'] }}</a>
                        @endforeach
                    </div>
                </details>

                <details class="dropdown">
                    <summary class="user-btn">
                        <span class="avatar">{{ $user->initials() }}</span>
                        <span class="user-meta hide-sm">
                            <strong>{{ $user->name }}</strong>
                            <span class="role-chip">{{ $user->role->label() }}</span>
                        </span>
                        <x-icon name="chevron-down" :size="14" />
                    </summary>
                    <div class="dropdown-menu dropdown-right">
                        <p class="dropdown-title">{{ $user->email }}</p>
                        <a class="dropdown-item" href="{{ route('admin.profile.edit') }}"><x-icon name="user" :size="16" /> {{ __('My profile') }}</a>
                        <form method="post" action="{{ route('logout') }}">
                            @csrf
                            <button class="dropdown-item" type="submit"><x-icon name="log-out" :size="16" /> {{ __('Sign out') }}</button>
                        </form>
                    </div>
                </details>
            </div>
        </header>

        <main class="content">
            @if (session('status'))
                <div class="toast toast-success" role="status" data-toast><x-icon name="circle-check" :size="18" /> {{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="toast toast-error" role="alert" data-toast><x-icon name="triangle-alert" :size="18" /> {{ session('error') }}</div>
            @endif

            @if ($back || $actions)
                <div class="page-bar">
                    @if ($back)
                        <a class="back-link" href="{{ $back }}"><x-icon name="arrow-left" :size="16" /> {{ __('Back') }}</a>
                    @else
                        <span></span>
                    @endif
                    @if ($actions)<div class="page-actions">{{ $actions }}</div>@endif
                </div>
            @endif

            {{ $slot }}
        </main>

        <footer class="footer">
            <span>© {{ date('Y') }} {{ \App\Models\Setting::get('legal_name') }}</span>
            <span>{{ __('Valuation system') }}</span>
        </footer>
    </div>
</div>
<script src="{{ asset('js/admin.js') }}?v={{ filemtime(public_path('js/admin.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
