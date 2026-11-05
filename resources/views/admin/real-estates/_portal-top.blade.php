{{-- The first part of the real estate page, in the two columns of the valuation portal. --}}
@php
    use App\Enums\ValuationApproach;
    use App\Enums\ValuationPurpose;
    use App\Reports\RealEstateReport;
    use App\Support\Format;
    $rv = RealEstateReport::values($item);
    $residential = ($item->kind ?? null) === \App\Enums\RealEstateKind::Residential;
    $choices = fn (string $key, ?array $only = null) => RealEstateReport::choices($key, $rv[$key] ?? null, $only);
    // The owner is chosen on its own; the other persons (by power of attorney, another organisation) under the tick.
    $ownerRow = collect($personRows)->firstWhere('role', \App\Enums\PersonRole::Owner->value);
    $otherRows = collect($personRows)->reject(fn ($row) => $row === $ownerRow)->values()->all();
    $applied = array_map('strval', array_filter((array) old('approaches', $item->approaches ?? []), 'filled'));
    $purpose = old('purpose', $item->purpose ?? ($item->exists ? null : ValuationPurpose::Collateral->value));
    $vatRate = (float) \App\Models\Setting::get('vat_rate');
    $money = fn ($v) => $v !== null && $v !== '' ? Format::number($v, fmod((float) $v, 1.0) ? 2 : 0) : '';
@endphp
<div class="portal-cols">
    <div class="portal-col form-grid">
        <x-select name="property_type" :label="__('Type of the property')" :options="$choices('property_type')" :value="$rv['property_type']" span="12" required />

        <div class="field span-12">
            <label for="f-owner">{{ __('Owner of the property') }}<span class="req">*</span></label>
            <div class="combo" data-client-pick data-search-url="{{ route('admin.clients.search') }}">
                <input type="hidden" name="owner_id" value="{{ $ownerRow['client_id'] ?? '' }}" data-combo-value>
                <div class="combo-selected" data-combo-selected @if (! $ownerRow) hidden @endif>
                    <span><strong>{{ $ownerRow['title'] ?? '' }}</strong><small>{{ $ownerRow['sub'] ?? '' }}</small></span>
                    <button type="button" class="btn btn-sm btn-ghost" data-combo-clear>{{ __('Change') }}</button>
                </div>
                <input id="f-owner" type="search" @class(['input', 'is-invalid' => $errors->has('owner_id')]) data-combo-input placeholder="{{ __('Search by name, passport, TIN or phone…') }}" autocomplete="off" @if ($ownerRow) hidden @endif>
            </div>
            @error('owner_id')<p class="field-error">{{ $message }}</p>@enderror
            <label class="tick"><input type="checkbox" data-others-toggle @checked($otherRows !== [])> <span>{{ __('By power of attorney / is an order of another organisation involved') }}</span></label>
        </div>
        <div class="field span-12" data-others @if ($otherRows === []) hidden @endif>
            @include('admin.objects._persons', ['personRows' => $otherRows])
        </div>

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

        <div class="field span-12 contract-sum" data-vat="{{ $vatRate }}">
            <label for="f-service_fee">{{ __('Contract sum') }}</label>
            <input id="f-service_fee" name="service_fee" type="text" inputmode="decimal" data-money autocomplete="off" @class(['input', 'is-invalid' => $errors->has('service_fee')])
                   value="{{ old('service_fee', $money($item->service_fee)) }}" data-contract-sum>
            @error('service_fee')<p class="field-error">{{ $message }}</p>@enderror
            <p class="contract-line">{{ __('With VAT') }}: <span data-contract-vat>{{ $item->service_fee !== null ? Format::number((float) $item->service_fee * (1 + $vatRate / 100)) : '' }}</span> {{ __('sum') }}</p>
            <p class="contract-line">
                <label for="f-paid_amount">{{ __('Paid amount') }}:</label>
                <input id="f-paid_amount" name="paid_amount" type="text" inputmode="decimal" data-money autocomplete="off" class="input-plain" value="{{ old('paid_amount', $money($item->paid_amount)) }}" size="12">
                {{ __('sum') }}
            </p>
        </div>

        <x-textarea name="identification" :label="__('Opinion of the appraiser on the identification')" :value="$rv['identification']" span="12" rows="2" />

        <div class="field span-12 portal-map">
            <label>{{ __('Where it is located') }}</label>
            <div class="form-grid">
                @include('admin.real-estates._map-picker', ['compact' => true])
            </div>
        </div>
    </div>
</div>
