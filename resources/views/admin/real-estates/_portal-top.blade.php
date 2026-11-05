{{-- The first card of the real estate page, field by field in the order of the valuation portal. --}}
@php
    use App\Reports\RealEstateReport;
    use App\Support\Format;
    $rv = RealEstateReport::values($item);
    $choices = fn (string $key) => RealEstateReport::choices($key, $rv[$key] ?? null);
    // The owner is chosen on its own; the other persons (by power of attorney, another organisation) under the box.
    $ownerRow = collect($personRows)->firstWhere('role', \App\Enums\PersonRole::Owner->value);
    $otherRows = collect($personRows)->reject(fn ($row) => $row === $ownerRow)->values()->all();
    $vatRate = (float) \App\Models\Setting::get('vat_rate');
    $debt = $item->service_fee > 0 ? max(0, (float) $item->service_fee - (float) $item->paid_amount) : null;
@endphp
<x-select name="property_type" :label="__('Type of the property')" :options="$choices('property_type')" :value="$rv['property_type']" span="6" required />

<div class="field span-6">
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
    <div class="owner-links">
        <label class="check check-sm portal-proxy">
            <input type="checkbox" data-others-toggle @checked($otherRows !== [])>
            <span class="check-box"><x-icon name="check" :size="12" /></span>
            <span>{{ __('By power of attorney / is an order of another organisation involved') }}</span>
        </label>
        <a class="link-sm" href="{{ route('admin.clients.create', ['type' => 'individual']) }}" target="_blank"><x-icon name="plus" :size="14" /> {{ __('New client') }}</a>
    </div>
</div>
<div class="field span-12" data-others @if ($otherRows === []) hidden @endif>
    @include('admin.objects._persons', ['personRows' => $otherRows])
</div>

<x-multi-select name="approaches" :label="__('Approach methods')" :options="$approachOptions" :value="$item->approaches ?? []" span="6" required />
<x-select name="purpose" :label="__('Purpose of valuation')" :options="$purposes" :value="$item->purpose" placeholder="—" span="6" required />

<x-field name="name" :label="__('Object')" :value="$item->name" span="6" required :placeholder="__('e.g. Non-residential building (shop)')" />
<div class="field span-6">
    <label for="f-cadastral_number">{{ __('Cadastral number of the real estate') }}<span class="req">*</span> <span class="label-note">({{ __('Press the button to get the cadastral data!') }})</span></label>
    <div class="input-row">
        <input id="f-cadastral_number" name="cadastral_number" value="{{ old('cadastral_number', $item->cadastral_number) }}" placeholder="10:01:05:01:02:0123" @class(['input', 'is-invalid' => $errors->has('cadastral_number')])>
        <button type="button" class="btn btn-info" data-reveal="cadastre-note">{{ __('Get the data') }}</button>
    </div>
    @error('cadastral_number')<p class="field-error">{{ $message }}</p>@enderror
    {{-- There is no connection to the cadastre agency: the data is read from the uploaded extract. --}}
    <p class="field-hint" id="cadastre-note" hidden>{{ __('There is no direct connection to the cadastre system. Upload the cadastral extract under "Documents": the data is read from it.') }}</p>
</div>

<x-multi-select name="features" :label="__('Special amenities')" :options="$features" :value="$item->features ?? []" span="6" required />
<x-select name="architecture" :label="__('Architectural style (facade)')" :options="$choices('architecture')" :value="$rv['architecture']" span="6" required />

<x-select name="function" :label="__('Purpose of use')" :options="$choices('function')" :value="$rv['function']" span="6" required />
<x-select name="repair" :label="__('Repair condition')" :options="$choices('repair')" :value="$rv['repair']" placeholder="—" span="6" required />

<x-field name="completion" type="number" :label="__('Construction completed (%)')" :value="number_format((float) $rv['completion'], 2, '.', '')" span="6" required min="0" max="100" />
<x-select name="appraiser_id" :label="__('Appraiser')" :options="$appraisers" :value="$item->appraiser_id" placeholder="—" span="6" />

<div class="field span-6">
    <input type="hidden" name="light" value="0">
    <label class="check check-sm portal-light">
        <input type="checkbox" name="light" value="1" @checked(old('light', $rv['light'] ?? false))>
        <span class="check-box"><x-icon name="check" :size="12" /></span>
        <span>{{ __('Light structural element') }}</span>
    </label>
</div>
<div class="field span-6 contract-sum" data-vat="{{ $vatRate }}">
    <label for="f-service_fee">{{ __('Contract sum') }}</label>
    <div class="input-wrap has-suffix">
        <input id="f-service_fee" name="service_fee" type="text" inputmode="decimal" data-money autocomplete="off" @class(['input', 'is-invalid' => $errors->has('service_fee')])
               value="{{ old('service_fee', $item->service_fee !== null ? Format::number($item->service_fee, fmod((float) $item->service_fee, 1.0) ? 2 : 0) : '') }}" data-contract-sum>
        <span class="input-suffix">{{ __('sum') }}</span>
    </div>
    @error('service_fee')<p class="field-error">{{ $message }}</p>@enderror
    <p class="contract-line">{{ __('With VAT') }}: <strong data-contract-vat>{{ $item->service_fee !== null ? Format::number((float) $item->service_fee * (1 + $vatRate / 100)) : '0' }}</strong> {{ __('sum') }}</p>
    <p class="contract-line">
        <label for="f-paid_amount">{{ __('Paid amount') }}:</label>
        <input id="f-paid_amount" name="paid_amount" type="text" inputmode="decimal" data-money autocomplete="off" class="input input-sm"
               value="{{ old('paid_amount', $item->paid_amount !== null ? Format::number($item->paid_amount) : '') }}"> {{ __('sum') }}
        @if ($debt)<span class="badge badge-warning">{{ __('Debt') }}: {{ Format::money($debt) }}</span>@endif
    </p>
</div>

<x-textarea name="identification" :label="__('Opinion of the appraiser on the identification')" :value="$rv['identification']" span="12" rows="3" />

<div class="field span-12 portal-map">
    <p class="portal-map-title">{{ __('Where it is located') }}</p>
    <div class="form-grid">
        @include('admin.real-estates._map-picker', ['mapSpans' => [3, 6]])
    </div>
    @if ($item->hasLocation())
        <div class="map-links">
            @foreach (\App\Support\StaticMap::links($item->latitude, $item->longitude) as $service => $url)
                <a class="btn btn-sm" href="{{ $url }}" target="_blank" rel="noopener noreferrer"><x-icon name="external-link" :size="14" /> {{ $service }}</a>
            @endforeach
        </div>
    @endif
</div>
