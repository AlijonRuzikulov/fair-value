{{-- The first part of the real estate page, in the two columns of the valuation portal. --}}
@php
    use App\Enums\ValuationApproach;
    use App\Enums\ValuationPurpose;
    use App\Reports\RealEstateReport;
    use App\Support\Format;
    $rv = RealEstateReport::values($item);
    $residential = ($item->kind ?? null) === \App\Enums\RealEstateKind::Residential;
    $choices = fn (string $key, ?array $only = null) => RealEstateReport::choices($key, $rv[$key] ?? null, $only);
    $applied = array_map('strval', array_filter((array) old('approaches', $item->approaches ?? []), 'filled'));
    $purpose = old('purpose', $item->purpose ?? ($item->exists ? null : ValuationPurpose::Collateral->value));
@endphp
<div class="portal-cols">
    <div class="portal-col form-grid">
        <x-select name="property_type" :label="__('Type of the property')" :options="$choices('property_type')" :value="$rv['property_type']" span="12" required />

        @include('admin.objects._portal-owner')

        <fieldset class="field span-12 portal-ticks">
            <legend>{{ __('Approach methods') }}<span class="req">*</span></legend>
            <input type="hidden" name="approaches[]" value="">
            <div class="tick-row">
                @foreach ([ValuationApproach::Cost, ValuationApproach::Income, ValuationApproach::Comparative] as $approach)
                    <label class="tick"><input type="checkbox" name="approaches[]" value="{{ $approach->value }}" @checked(in_array($approach->value, $applied, true))> <span>{{ $approach->label() }}</span></label>
                @endforeach
            </div>
            @if ($errors->has('approaches'))<p class="field-error">{{ $errors->first('approaches') }}</p>@endif
        </fieldset>

        <x-select name="purpose" :label="__('Purpose of valuation')" :options="ValuationPurpose::portalOptions($purpose)" :value="$purpose" span="12" required />
        <x-field name="name" :label="__('Object')" :value="$item->name" span="12" required />

        <div class="field span-12">
            <label for="f-cadastral_number">{{ __('Cadastral number of the real estate') }}<span class="req">*</span><span class="label-alert">({{ __('Press the button to get the cadastral data!') }})</span></label>
            <div class="input-row joined">
                <input id="f-cadastral_number" name="cadastral_number" value="{{ old('cadastral_number', $item->cadastral_number) }}" @class(['input', 'is-invalid' => $errors->has('cadastral_number')])>
                <button type="button" class="btn btn-info" data-reveal="cadastre-note">{{ __('Get the data') }}</button>
            </div>
            @error('cadastral_number')<p class="field-error">{{ $message }}</p>@enderror
            {{-- There is no connection to the cadastre agency: the data is read from the uploaded extract. --}}
            <p class="field-hint" id="cadastre-note" hidden>{{ __('There is no direct connection to the cadastre system. Upload the cadastral extract under "Documents": the data is read from it.') }}</p>
        </div>

        <x-multi-select name="features" :label="__('Special amenities')" :options="$features" :value="$item->features ?? []" span="12" required placeholder="" />
        <x-select name="architecture" :label="__('Architectural style (facade)')" :options="$choices('architecture')" :value="$rv['architecture']" span="12" required />
    </div>

    <div class="portal-col form-grid">
        <x-select name="function" :label="__('Purpose of use')" :options="$choices('function', RealEstateReport::OPTIONS[$residential ? 'function_residential' : 'function_nonresidential'])" :value="$rv['function']" span="12" required />
        <x-select name="repair" :label="__('Repair condition')" :options="$choices('repair')" :value="$rv['repair']" placeholder="" span="12" required />
        <x-field name="completion" type="number" :label="__('Construction completed (%)')" :value="number_format((float) $rv['completion'], 2, '.', '')" span="12" required min="0" max="100" />
        <x-select name="appraiser_id" :label="__('Appraiser')" :options="$appraisers" :value="$item->appraiser_id" placeholder="" span="12" />

        <div class="field span-12">
            <input type="hidden" name="light" value="0">
            <label class="tick"><input type="checkbox" name="light" value="1" @checked(old('light', $rv['light'] ?? false))> <span>{{ __('Light structural element') }}</span></label>
        </div>

        @include('admin.objects._portal-contract')

        <x-textarea name="identification" :label="__('Opinion of the appraiser on the identification')" :value="$rv['identification']" span="12" rows="2" />

        <div class="field span-12 portal-map">
            <label>{{ __('Where it is located') }}</label>
            <div class="form-grid">
                @include('admin.real-estates._map-picker', ['compact' => true])
            </div>
        </div>
    </div>
</div>
