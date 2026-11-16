@props(['name', 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null, 'span' => 6, 'required' => false, 'hint' => null])
@php
    $id = 'f-'.str_replace(['[', ']', '.'], '-', $name);
    // "amenities[]": one choice sent as a list; its old input and errors are under "amenities".
    $key = rtrim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $current = old($key, $value instanceof \BackedEnum ? $value->value : $value);
    if (is_array($current)) {
        $current = collect($current)->first(fn ($v) => filled($v));
    }
@endphp
<div class="field span-{{ $span }}">
    @if ($label)<label for="{{ $id }}">{{ $label }}@if ($required)<span class="req">*</span>@endif</label>@endif
    <select id="{{ $id }}" name="{{ $name }}" @required($required) {{ $attributes->class(['input', 'is-invalid' => $errors->has($key)]) }}>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $current === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @error($key)<p class="field-error">{{ $message }}</p>@enderror
    @if ($hint)<p class="field-hint">{{ $hint }}</p>@endif
</div>
