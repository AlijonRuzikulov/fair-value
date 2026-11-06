{{-- The value of the object: the approaches applied with their values and the final market value. --}}
@php $applied = array_map('strval', array_filter((array) old('approaches', $item->approaches ?? []), 'filled')); @endphp
<details class="portal-fold" id="valuation" data-remember @if ($item->market_value !== null) open @endif>
    <summary>
        {{ __('Valuation') }}
        @if ($item->market_value !== null)<span class="portal-sum">{{ \App\Support\Format::money($item->market_value) }}</span>@endif
    </summary>
    <fieldset class="portal-fields" @disabled(! $canEdit)>
        <div class="form-grid">
            <input type="hidden" name="approaches[]" value="">
            @foreach ($approachOptions as $value => $label)
                <div class="field span-4">
                    <label class="tick"><input type="checkbox" name="approaches[]" value="{{ $value }}" @checked(in_array($value, $applied, true))> <span>{{ $label }}</span></label>
                    <x-field :name="'value_'.$value" :value="$item->{'value_'.$value}" money :suffix="__('sum')" span="12" :aria-label="$label" />
                </div>
            @endforeach
            <x-field name="market_value" :label="__('Market value (final)')" :value="$item->market_value" money :suffix="__('sum')" span="4" />
            <x-field name="valuation_date" type="date" :label="__('Valuation date')" :value="$item->valuation_date" span="4" />
            <x-field name="inspection_date" type="date" :label="__('Inspection date')" :value="$item->inspection_date" span="4" />
        </div>
    </fieldset>
</details>
