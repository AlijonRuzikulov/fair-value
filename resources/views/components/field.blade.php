@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'hint' => null, 'span' => 6, 'required' => false, 'money' => false, 'suffix' => null])
@php
    $id = 'f-'.str_replace(['[', ']', '.'], '-', $name);
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $current = old($key, $value instanceof \Carbon\CarbonInterface ? $value->format('Y-m-d') : $value);
    if ($money && $current !== null && $current !== '' && is_numeric($current)) {
        $current = \App\Support\Format::number($current, fmod((float) $current, 1.0) ? 2 : 0);
    }
@endphp
<div class="field span-{{ $span }}">
    @if ($label)<label for="{{ $id }}">{{ $label }}@if ($required)<span class="req">*</span>@endif</label>@endif
    <div @class(['input-wrap', 'has-suffix' => $suffix])>
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $money ? 'text' : $type }}" value="{{ $current }}"
            @if ($money) inputmode="decimal" data-money autocomplete="off" @endif
            @if ($type === 'number') step="any" @endif
            @required($required)
            {{ $attributes->class(['input', 'is-invalid' => $errors->has($key)]) }}>
        @if ($suffix)<span class="input-suffix">{{ $suffix }}</span>@endif
    </div>
    @error($key)<p class="field-error">{{ $message }}</p>@enderror
    @if ($hint)<p class="field-hint">{{ $hint }}</p>@endif
</div>
