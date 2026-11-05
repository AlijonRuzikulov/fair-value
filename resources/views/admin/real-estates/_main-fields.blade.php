{{-- "Ko'chmas mulk asosiy ma'lumotlari" in the two columns of the valuation portal. --}}
@php
    use App\Reports\RealEstateReport;
    $rv = RealEstateReport::values($item);
    $market = old('value_kind', $rv['value_type'] === RealEstateReport::MARKET_VALUE ? 'market' : 'other') === 'market';
    $otherTypes = array_values(array_diff(RealEstateReport::OPTIONS['value_type'], [RealEstateReport::MARKET_VALUE]));
    $otherValue = old('value_type', $market ? null : $rv['value_type']);
    // Storeys 1–60, as on the portal (a value saved before stays in the list).
    $storeys = fn ($current) => collect(range(1, 60))->when(filled($current) && ((int) $current < 1 || (int) $current > 60), fn ($c) => $c->prepend((int) $current))
        ->mapWithKeys(fn ($n) => [$n => __(':n storey', ['n' => $n])])->all();
    $index = $rv['market_index'] ?? null;
    $useIndex = (bool) old('use_index', $index !== null && $index !== '');
@endphp
<div class="portal-cols">
    <div class="portal-col form-grid">
        <x-field name="cadastral_value" :label="__('Cadastral or book value')" :value="$item->cadastral_value" money span="12" required />
        <x-select name="region_id" :label="__('Select the region')" :options="$regions->pluck('name', 'id')" :value="$item->region_id" placeholder="" span="12" required data-region-select />
        <x-select name="district_id" :label="__('Select the district')" :options="$districts" :value="$item->district_id" placeholder="" span="12" required data-district-select />
        <x-field name="landmark" :label="__('Select the landmark')" :value="$item->landmark" span="12" list="landmarks" required class="input-select" />
        <x-field name="address" :label="__('Address of the real estate')" :value="$item->address" span="12" required />
        {{-- The numbers are given by the system: the letter takes the contract number, the report one on completion. --}}
        <div class="field span-12 number-box">
            <p><span>{{ __('Letter №') }}:</span> {{ $rv['letter_number'] }}</p>
            @if ($item->exists)
                <p><span>{{ __('Contract №') }}:</span> {{ $item->contract_number }}@if ($item->contract_date) · {{ \App\Support\Format::date($item->contract_date) }}@endif</p>
            @else
                <p><span>{{ __('Contract №') }}:</span> <span class="number-next" title="{{ __('Leave empty to get the next number.') }}">{{ \App\Support\DocumentNumber::next('contract') }}</span></p>
            @endif
            <p><span>{{ __('Report №') }}:</span> {{ $item->report_number }}</p>
        </div>
    </div>

    <div class="portal-col form-grid">
        <x-field name="land_area" :label="__('Total land area of the real estate (m²)')" :value="$item->land_area" money span="12" required />
        <x-field name="built_up_area" :label="__('Total built-up area of the real estate (m²)')" :value="$item->built_up_area" money span="12" required />
        <x-field name="useful_area" :label="__('Total useful area of the real estate (m²)')" :value="$item->useful_area" money span="12" required />
        <div class="field span-12">
            <label for="f-total_area">{{ __('Total area of the real estate (m²)') }}</label>
            <div class="total-area-row">
                <input id="f-total_area" name="total_area" type="text" inputmode="decimal" data-money autocomplete="off" @class(['input', 'is-invalid' => $errors->has('total_area')])
                       value="{{ old('total_area', $item->total_area !== null ? \App\Support\Format::number($item->total_area, fmod((float) $item->total_area, 1.0) ? 2 : 0) : '') }}">
                {{-- A helper of the form only: while ticked, the total area is copied into the other areas. --}}
                <label class="tick tick-caps area-copy"><input type="checkbox" data-area-copy> <span>{{ __('Calculate from the total area') }}</span></label>
            </div>
            @error('total_area')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <x-select name="wall_material" :label="__('Type of construction')" :options="RealEstateReport::choices('construction', $item->wall_material)" :value="$item->wall_material" placeholder="" span="12" required />
        <div class="field span-12">
            <input type="hidden" name="detached" value="0">
            <label class="tick"><input type="checkbox" name="detached" value="1" @checked(old('detached', $rv['detached'] ?? false))> <span>{{ __('Detached building') }}</span></label>
        </div>
        <x-select name="floors_total" :label="__('Number of storeys')" :options="$storeys($item->floors_total)" :value="$item->exists ? $item->floors_total : 1" placeholder="" span="12" />
        <x-select name="floor" :label="__('Storey')" :options="$storeys($item->floor)" :value="$item->exists ? $item->floor : 1" placeholder="" span="12" />
        <x-field name="year_built" type="number" :label="__('Year built')" :value="$item->year_built" span="12" min="1800" />

        {{-- The market value, or another type of value chosen from the list. --}}
        <fieldset class="field span-12 portal-ticks" data-value-kind>
            <legend>{{ __('Choose the type of value being determined for the real estate') }}<span class="req">*</span></legend>
            <div class="tick-row">
                <label class="tick"><input type="radio" name="value_kind" value="market" @checked($market)> <span>{{ __('Market value') }}</span></label>
                <label class="tick"><input type="radio" name="value_kind" value="other" @checked(! $market)> <span>{{ __('Other than the market value') }}</span></label>
                <select class="input input-sm @error('value_type') is-invalid @enderror" name="value_type" aria-label="{{ __('Type of value') }}" data-value-other @if ($market) hidden disabled @endif>
                    <option value="">—</option>
                    @foreach (RealEstateReport::choices('value_type', $otherValue, $otherTypes) as $option => $label)
                        <option value="{{ $option }}" @selected($otherValue === $option)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @error('value_type')<p class="field-error">{{ $message }}</p>@enderror
        </fieldset>

        <x-select name="ownership_form" :label="__('Form of ownership')" :options="RealEstateReport::choices('ownership_form', $rv['ownership_form'])" :value="$rv['ownership_form']" span="12" required />
        <x-select name="land_rights" :label="__('Ownership right to the land plot')" :options="RealEstateReport::choices('land_rights', $rv['land_rights'])" :value="$rv['land_rights']" span="12" required />

        {{-- The price index of the comparative approach (also on its grid). --}}
        <div class="field span-12 portal-index" data-index>
            <input type="hidden" name="use_index" value="0">
            <label class="tick"><input type="checkbox" name="use_index" value="1" @checked($useIndex) data-index-toggle> <span>{{ __('Adjust the comparative approach by the index') }}</span></label>
            <span class="portal-index-value" @if (! $useIndex) hidden @endif data-index-value>
                <label for="f-portal-index">{{ __('Price index, % per month') }}</label>
                <input id="f-portal-index" name="market_index" inputmode="decimal" class="input input-sm @error('market_index') is-invalid @enderror"
                       value="{{ old('market_index', $index !== null && $index !== '' ? rtrim(rtrim(number_format((float) $index, 2, '.', ''), '0'), '.') : '') }}" @disabled(! $useIndex)>
            </span>
            @error('market_index')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        @if ($item->exists)
            <div class="field span-12">
                <div class="btn-group">
                    <a class="btn btn-success" href="{{ route('admin.real-estates.report.edit', $item->id) }}#step-inspection"><x-icon name="list" :size="16" /> {{ __('Enter the data of the act') }}</a>
                    <a class="btn btn-info" href="{{ route('admin.real-estates.report.edit', $item->id) }}#survey"><x-icon name="list" :size="16" /> {{ __('Questionnaire data') }}</a>
                </div>
            </div>
        @endif
    </div>
</div>

<datalist id="landmarks">@foreach ($landmarks ?? [] as $landmark)<option value="{{ $landmark }}">@endforeach</datalist>
