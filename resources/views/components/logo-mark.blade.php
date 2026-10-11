@props(['size' => 40])
{{-- Fair Value mark: a diamond (value, stability) with a rising line (growth). Replace with the official logo if needed. --}}
<svg {{ $attributes }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 48 48" aria-hidden="true">
    <defs>
        <linearGradient id="fv-g" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#FFB547"/>
            <stop offset="1" stop-color="#E8601C"/>
        </linearGradient>
    </defs>
    <rect x="9" y="9" width="30" height="30" rx="7" transform="rotate(45 24 24)" fill="url(#fv-g)"/>
    <path d="M13.5 28.5 20 22l5 4.5 9.5-9.5" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M29 17h5.5v5.5" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
