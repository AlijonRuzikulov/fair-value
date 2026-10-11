@props(['name', 'label' => null, 'value' => null, 'span' => 12, 'rows' => 3, 'hint' => null])
@php $id = 'f-'.$name; @endphp
<div class="field span-{{ $span }}">
    @if ($label)<label for="{{ $id }}">{{ $label }}</label>@endif
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" {{ $attributes->class(['input', 'is-invalid' => $errors->has($name)]) }}>{{ old($name, $value) }}</textarea>
    @error($name)<p class="field-error">{{ $message }}</p>@enderror
    @if ($hint)<p class="field-hint">{{ $hint }}</p>@endif
</div>
