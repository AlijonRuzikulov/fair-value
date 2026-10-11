@props(['name', 'label' => null, 'options' => [], 'value' => [], 'span' => 12])
@php $current = array_map('strval', (array) old($name, $value ?? [])); @endphp
<fieldset class="field span-{{ $span }}">
    @if ($label)<legend>{{ $label }}</legend>@endif
    <div {{ $attributes->merge(['class' => 'check-grid']) }}>
        @foreach ($options as $optionValue => $optionLabel)
            <label class="check">
                <input type="checkbox" name="{{ $name }}[]" value="{{ $optionValue }}" @checked(in_array((string) $optionValue, $current, true))>
                <span class="check-box"><x-icon name="check" :size="14" /></span>
                <span>{{ $optionLabel }}</span>
            </label>
        @endforeach
    </div>
    @error($name)<p class="field-error">{{ $message }}</p>@enderror
</fieldset>
