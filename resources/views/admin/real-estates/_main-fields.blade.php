{{-- "Ko'chmas mulk asosiy ma'lumotlari" in the order of the valuation portal; the data the report needs besides follows under its own title. --}}
@php
    use App\Reports\RealEstateReport;
    $rv = RealEstateReport::values($item);
    $kind = old('kind', $item->kind?->value);
    $market = old('value_kind', $rv['value_type'] === RealEstateReport::MARKET_VALUE ? 'market' : 'other') === 'market';
    $otherTypes = array_values(array_diff(RealEstateReport::OPTIONS['value_type'], [RealEstateReport::MARKET_VALUE]));
    $otherValue = old('value_type', $market ? null : $rv['value_type']);
    // Storeys 1–60, as on the portal (a value saved before stays in the list).
    $storeys = fn ($current) => collect(range(1, 60))->when(filled($current) && ((int) $current < 1 || (int) $current > 60), fn ($c) => $c->prepend((int) $current))
        ->mapWithKeys(fn ($n) => [$n => __(':n storey', ['n' => $n])])->all();
    $index = $rv['market_index'] ?? null;
    $useIndex = (bool) old('use_index', $index !== null && $index !== '');
@endphp
<x-field name="cadastral_value" :label="__('Cadastral or book value')" :value="$item->cadastral_value" money :suffix="__('sum')" span="6" required />
<x-select name="region_id" :label="__('Select the region')" :options="$regions->pluck('name', 'id')" :value="$item->region_id" placeholder="—" span="6" required data-region-select />
<x-select name="district_id" :label="__('Select the district')" :options="$districts" :value="$item->district_id" placeholder="—" span="6" required data-district-select />
<x-field name="landmark" :label="__('Select the landmark')" :value="$item->landmark" span="6" list="landmarks" required :placeholder="__('e.g. Feruza massif')" />
<x-field name="address" :label="__('Address of the real estate')" :value="$item->address" span="12" required :placeholder="__('Street, house, apartment')" />

<div class="portal-box span-12">
    <div class="form-grid">
        <x-field name="letter_number" :label="__('Letter №')" :value="$item->report['letter_number'] ?? null" span="4" :placeholder="$item->contract_number" :hint="__('The contract number when left empty.')" />
        <x-field name="contract_number" :label="__('Contract №')" :value="$item->contract_number" span="4"
                 :placeholder="$item->exists ? null : \App\Support\DocumentNumber::next('contract')" :hint="$item->exists ? null : __('Leave empty to get the next number.')" />
        <x-field name="report_number" :label="__('Report №')" :value="$item->report_number" span="4" :hint="__('Assigned automatically when completed.')" />
    </div>
</div>

<x-field name="land_area" :label="__('Total land area of the real estate (m²)')" :value="$item->land_area" money :suffix="__('m²')" span="6" required />
<x-field name="built_up_area" :label="__('Total built-up area of the real estate (m²)')" :value="$item->built_up_area" money :suffix="__('m²')" span="6" required />
<x-field name="useful_area" :label="__('Total useful area of the real estate (m²)')" :value="$item->useful_area" money :suffix="__('m²')" span="6" required />
<x-field name="total_area" :label="__('Total area of the real estate (m²)')" :value="$item->total_area" money :suffix="__('m²')" span="6" />
{{-- A helper of the form only: while ticked, the total area is copied into the other areas. --}}
<div class="field span-12 area-copy">
    <label class="check check-sm">
        <input type="checkbox" data-area-copy>
        <span class="check-box"><x-icon name="check" :size="12" /></span>
        <span>{{ __('Calculate from the total area') }}</span>
    </label>
</div>

<x-select name="wall_material" :label="__('Type of construction')" :options="RealEstateReport::choices('construction', $item->wall_material)" :value="$item->wall_material" placeholder="—" span="6" required />
<div class="field span-6">
    <input type="hidden" name="detached" value="0">
    <label class="check check-sm portal-light">
        <input type="checkbox" name="detached" value="1" @checked(old('detached', $rv['detached'] ?? false))>
        <span class="check-box"><x-icon name="check" :size="12" /></span>
        <span>{{ __('Detached building') }}</span>
    </label>
</div>
<x-select name="floors_total" :label="__('Number of storeys')" :options="$storeys($item->floors_total)" :value="$item->exists ? $item->floors_total : 1" placeholder="—" span="4" />
<x-select name="floor" :label="__('Storey')" :options="$storeys($item->floor)" :value="$item->exists ? $item->floor : 1" placeholder="—" span="4" />
<x-field name="year_built" type="number" :label="__('Year built')" :value="$item->year_built" span="4" min="1800" />

{{-- The market value, or another type of value chosen from the list. --}}
<fieldset class="field span-12 portal-radios" data-value-kind>
    <legend>{{ __('Choose the type of value being determined for the real estate') }}<span class="req">*</span></legend>
    <div class="radio-row">
        <label class="check"><input type="radio" name="value_kind" value="market" @checked($market)><span class="radio-dot"></span><span>{{ __('Market value') }}</span></label>
        <label class="check"><input type="radio" name="value_kind" value="other" @checked(! $market)><span class="radio-dot"></span><span>{{ __('Other than the market value') }}</span></label>
        <select class="input @error('value_type') is-invalid @enderror" name="value_type" aria-label="{{ __('Type of value') }}" data-value-other @if ($market) hidden disabled @endif>
            <option value="">—</option>
            @foreach (RealEstateReport::choices('value_type', $otherValue, $otherTypes) as $option => $label)
                <option value="{{ $option }}" @selected($otherValue === $option)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    @error('value_type')<p class="field-error">{{ $message }}</p>@enderror
</fieldset>

<x-select name="ownership_form" :label="__('Form of ownership')" :options="RealEstateReport::choices('ownership_form', $rv['ownership_form'])" :value="$rv['ownership_form']" span="6" required />
<x-select name="land_rights" :label="__('Ownership right to the land plot')" :options="RealEstateReport::choices('land_rights', $rv['land_rights'])" :value="$rv['land_rights']" span="6" required />

{{-- The price index of the comparative approach (also on its grid). --}}
<div class="field span-12 portal-index" data-index>
    <input type="hidden" name="use_index" value="0">
    <label class="check check-sm portal-light">
        <input type="checkbox" name="use_index" value="1" @checked($useIndex) data-index-toggle>
        <span class="check-box"><x-icon name="check" :size="12" /></span>
        <span>{{ __('Adjust the comparative approach by the index') }}</span>
    </label>
    <span class="portal-index-value" @if (! $useIndex) hidden @endif data-index-value>
        <label for="f-portal-index">{{ __('Price index, % per month') }}</label>
        <input id="f-portal-index" name="market_index" inputmode="decimal" class="input input-sm @error('market_index') is-invalid @enderror"
               value="{{ old('market_index', $index !== null && $index !== '' ? rtrim(rtrim(number_format((float) $index, 2, '.', ''), '0'), '.') : '') }}" @disabled(! $useIndex)>
    </span>
    @error('market_index')<p class="field-error">{{ $message }}</p>@enderror
</div>

@if ($item->exists)
    <div class="field span-12 portal-actions portal-act-buttons">
        <a class="btn btn-success" href="{{ route('admin.real-estates.report.edit', $item->id) }}#step-inspection"><x-icon name="clipboard-check" :size="16" /> {{ __('Enter the data of the act') }}</a>
        <a class="btn btn-info" href="{{ route('admin.real-estates.report.edit', $item->id) }}#survey"><x-icon name="list-checks" :size="16" /> {{ __('Questionnaire data') }}</a>
    </div>
@endif

{{-- Not on the portal's form, but printed in the report. --}}
<p class="form-section-title">{{ __('More data for the report') }}</p>
<div class="field span-3" data-kind-only="residential" @if ($kind !== 'residential') hidden @endif>
    <x-field name="living_area" :label="__('Living area')" :value="$item->living_area" money :suffix="__('m²')" span="12" />
</div>
<x-field name="rooms" type="number" :label="__('Rooms')" :value="$item->rooms" span="3" min="0" />
<x-field name="condition" :label="__('Technical condition')" :value="$item->condition" span="3" list="conditions" />
<x-field name="contract_date" type="date" :label="__('Contract date')" :value="$item->contract_date" span="3" />
<x-field name="valuation_date" type="date" :label="__('Valuation date')" :value="$item->valuation_date" span="3" />
<x-field name="inspection_date" type="date" :label="__('Inspection date')" :value="$item->inspection_date" span="3" />
<x-textarea name="notes" :label="__('Notes')" :value="$item->notes" span="12" rows="2" />

<datalist id="landmarks">@foreach ($landmarks ?? [] as $landmark)<option value="{{ $landmark }}">@endforeach</datalist>
<datalist id="conditions"><option value="{{ __('Excellent') }}"><option value="{{ __('Good') }}"><option value="{{ __('Satisfactory') }}"><option value="{{ __('Needs repair') }}"></datalist>
