{{-- A dropdown of checkboxes shown as tags (the multiple select of the valuation portal). An empty first value tells "none chosen" from "not on the form". --}}
@props(['name', 'label' => null, 'options' => [], 'value' => [], 'span' => 6, 'required' => false, 'placeholder' => '—'])
@php
    $current = array_map('strval', array_filter((array) old($name, $value ?? []), 'filled'));
    $invalid = $errors->has($name) || $errors->has($name.'.*');
@endphp
<fieldset class="field span-{{ $span }} multi-field">
    @if ($label)<legend>{{ $label }}@if ($required)<span class="req">*</span>@endif</legend>@endif
    <details class="multi" data-multi>
        <summary @class(['input', 'multi-box', 'is-invalid' => $invalid])>
            <span class="multi-tags" data-multi-tags>
                @foreach ($options as $optionValue => $optionLabel)
                    @if (in_array((string) $optionValue, $current, true))<span class="multi-tag">{{ $optionLabel }}</span>@endif
                @endforeach
            </span>
            <span class="multi-placeholder" data-multi-placeholder @if ($current) hidden @endif>{{ $placeholder }}</span>
        </summary>
        <div class="multi-menu">
            <input type="hidden" name="{{ $name }}[]" value="">
            @foreach ($options as $optionValue => $optionLabel)
                <label class="multi-option">
                    <input type="checkbox" name="{{ $name }}[]" value="{{ $optionValue }}" data-label="{{ $optionLabel }}" @checked(in_array((string) $optionValue, $current, true))>
                    <span>{{ $optionLabel }}</span>
                </label>
            @endforeach
        </div>
    </details>
    @if ($errors->has($name))<p class="field-error">{{ $errors->first($name) }}</p>@elseif ($errors->has($name.'.*'))<p class="field-error">{{ $errors->first($name.'.*') }}</p>@endif
</fieldset>
