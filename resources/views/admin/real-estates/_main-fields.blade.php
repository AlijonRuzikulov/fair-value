{{-- "Ko'chmas mulk asosiy ma'lumotlari": the place, the numbers, the areas, the building, the value and the rights. --}}
@php
    use App\Reports\RealEstateReport;
    $rv = RealEstateReport::values($item);
    $kind = old('kind', $item->kind?->value);
    $market = old('value_kind', $rv['value_type'] === RealEstateReport::MARKET_VALUE ? 'market' : 'other') === 'market';
    $otherTypes = array_values(array_diff(RealEstateReport::OPTIONS['value_type'], [RealEstateReport::MARKET_VALUE]));
    $otherValue = old('value_type', $market ? null : $rv['value_type']);
@endphp
<x-select name="region_id" :label="__('Region')" :options="$regions->pluck('name', 'id')" :value="$item->region_id" placeholder="—" span="6" data-region-select />
<x-select name="district_id" :label="__('District / city')" :options="$districts" :value="$item->district_id" placeholder="—" span="6" data-district-select />
<x-field name="landmark" :label="__('Landmark (massif, neighbourhood)')" :value="$item->landmark" span="6" list="landmarks" :placeholder="__('e.g. Feruza massif')" />
<x-field name="address" :label="__('Address')" :value="$item->address" span="6" :placeholder="__('Street, house, apartment')" />

<div class="portal-box span-12">
    <div class="form-grid">
        <x-field name="letter_number" :label="__('Letter number')" :value="$item->report['letter_number'] ?? null" span="3" :placeholder="$item->contract_number" :hint="__('The contract number when left empty.')" />
        <x-field name="contract_number" :label="__('Contract number')" :value="$item->contract_number" span="3"
                 :placeholder="$item->exists ? null : \App\Support\DocumentNumber::next('contract')" :hint="$item->exists ? null : __('Leave empty to get the next number.')" />
        <x-field name="contract_date" type="date" :label="__('Contract date')" :value="$item->contract_date" span="3" />
        <x-field name="report_number" :label="__('Report number')" :value="$item->report_number" span="3" :hint="__('Assigned automatically when completed.')" />
    </div>
</div>

<x-field name="cadastral_value" :label="__('Cadastral or book value')" :value="$item->cadastral_value" money :suffix="__('sum')" span="6" />
<fieldset class="field span-6 portal-radios" data-value-kind>
    <legend>{{ __('Type of value') }}</legend>
    <div class="radio-row">
        <label class="check"><input type="radio" name="value_kind" value="market" @checked($market)><span class="radio-dot"></span><span>{{ __('Market value') }}</span></label>
        <label class="check"><input type="radio" name="value_kind" value="other" @checked(! $market)><span class="radio-dot"></span><span>{{ __('Other than the market value') }}</span></label>
    </div>
    <select class="input @error('value_type') is-invalid @enderror" name="value_type" aria-label="{{ __('Type of value') }}" data-value-other @if ($market) hidden disabled @endif>
        <option value="">—</option>
        @foreach (RealEstateReport::choices('value_type', $otherValue, $otherTypes) as $option => $label)
            <option value="{{ $option }}" @selected($otherValue === $option)>{{ $label }}</option>
        @endforeach
    </select>
    @error('value_type')<p class="field-error">{{ $message }}</p>@enderror
</fieldset>

<x-field name="land_area" :label="__('Land area')" :value="$item->land_area" money :suffix="__('m²')" span="3" />
<x-field name="built_up_area" :label="__('Built-up area')" :value="$item->built_up_area" money :suffix="__('m²')" span="3" />
<x-field name="useful_area" :label="__('Useful area')" :value="$item->useful_area" money :suffix="__('m²')" span="3" />
<x-field name="total_area" :label="__('Total area')" :value="$item->total_area" money :suffix="__('m²')" span="3" />
{{-- A helper of the form only: while ticked, the total area is copied into the other areas. --}}
<div class="field span-12 area-copy">
    <label class="check check-sm">
        <input type="checkbox" data-area-copy>
        <span class="check-box"><x-icon name="check" :size="12" /></span>
        <span>{{ __('The useful, land and built-up areas are the total area (as in a flat)') }}</span>
    </label>
</div>
<div class="field span-3" data-kind-only="residential" @if ($kind !== 'residential') hidden @endif>
    <x-field name="living_area" :label="__('Living area')" :value="$item->living_area" money :suffix="__('m²')" span="12" />
</div>
<x-field name="rooms" type="number" :label="__('Rooms')" :value="$item->rooms" span="3" min="0" />
<x-field name="wall_material" :label="__('Type of construction (walls)')" :value="$item->wall_material" span="3" :placeholder="__('Brick, panel, monolith…')" list="wall-materials" />
<x-field name="condition" :label="__('Technical condition')" :value="$item->condition" span="3" list="conditions" />

<x-field name="floors_total" type="number" :label="__('Number of floors')" :value="$item->floors_total" span="3" min="0" />
<x-field name="floor" type="number" :label="__('Floor')" :value="$item->floor" span="3" />
<x-field name="year_built" type="number" :label="__('Year built')" :value="$item->year_built" span="3" min="1800" />
<x-field name="valuation_date" type="date" :label="__('Valuation date')" :value="$item->valuation_date" span="3" />

<x-select name="ownership_form" :label="__('Form of ownership')" :options="RealEstateReport::choices('ownership_form', $rv['ownership_form'])" :value="$rv['ownership_form']" span="4" />
<x-select name="land_rights" :label="__('Rights to the land plot')" :options="RealEstateReport::choices('land_rights', $rv['land_rights'])" :value="$rv['land_rights']" span="4" />
<x-field name="inspection_date" type="date" :label="__('Inspection date')" :value="$item->inspection_date" span="4" />

<x-textarea name="notes" :label="__('Notes')" :value="$item->notes" span="12" rows="2" />

<datalist id="landmarks">@foreach ($landmarks ?? [] as $landmark)<option value="{{ $landmark }}">@endforeach</datalist>
<datalist id="wall-materials"><option value="{{ __('Brick') }}"><option value="{{ __('Panel') }}"><option value="{{ __('Monolith') }}"><option value="{{ __('Block') }}"><option value="{{ __('Wood') }}"></datalist>
<datalist id="conditions"><option value="{{ __('Excellent') }}"><option value="{{ __('Good') }}"><option value="{{ __('Satisfactory') }}"><option value="{{ __('Needs repair') }}"></datalist>
