@props(['name', 'size' => 18])
<svg {{ $attributes->merge(['class' => 'icon']) }} width="{{ $size }}" height="{{ $size }}" aria-hidden="true" focusable="false"><use href="{{ asset('images/icons.svg') }}#i-{{ $name }}"></use></svg>
