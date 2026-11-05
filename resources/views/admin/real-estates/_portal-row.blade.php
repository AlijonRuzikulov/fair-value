{{-- A row of the real estate list as on the valuation portal: everything about the valuation at a glance, and the buttons of the photos and the editing. --}}
@php
    use App\Enums\PersonRole;
    use App\Enums\ValuationPurpose;
    use App\Enums\ValuationStatus;
    use App\Reports\RealEstateReport;
    use App\Support\Format;
    $rv = RealEstateReport::values($item);
    $vatRate = (float) \App\Models\Setting::get('vat_rate');
    $perM2 = fn ($value, $area) => $value !== null && (float) $area > 0 ? Format::number((float) $value / (float) $area, 2) : '0';
    $land = (float) ($item->report['cost']['land'] ?? 0);
    $buildings = count((array) ($item->report['cost']['buildings'] ?? []));
    $purpose = $item->purpose ? ValuationPurpose::tryFrom($item->purpose) : null;
    $features = $item->featureList();
    $byAppraiser = $item->currentStep('submitted');
    $byDirector = $item->currentStep('approved') ?? ($item->status === ValuationStatus::Completed && $item->completed_at ? true : null);
    $show = route($module->routeName('show'), $item->id);
@endphp
<tr class="portal-row">
    <td class="row-id">{{ $item->id }}@if ($purpose)<strong>{{ $purpose->shortLabel() }}</strong>@endif</td>
    <td class="row-object">
        <a class="cell-title" href="{{ $show }}">{{ $item->name }}</a>
        <span class="row-address">{{ $item->locationLine() ?: '—' }}</span>
        @if ($item->service_fee !== null)
            <em>{{ __('Contract sum + VAT (:rate%)', ['rate' => Format::number($vatRate)]) }}: {{ Format::number((float) $item->service_fee * (1 + $vatRate / 100)) }}</em>
        @endif
    </td>
    <td class="row-lines">
        <span>{{ __('Total land area (m²)') }}: <b>{{ $item->land_area !== null ? Format::number($item->land_area, 2) : '—' }}</b></span>
        <span>{{ __('Price of 1 m²') }} {{ $perM2($land ?: null, $item->land_area) }}</span>
        <span>{{ __('Useful area (m²)') }}: <b>{{ $item->useful_area !== null ? Format::number($item->useful_area, 2) : '—' }}</b></span>
        <span>{{ __('Price of 1 m²') }} <b>{{ $perM2($item->market_value, $item->useful_area ?: $item->total_area) }} {{ __('sum') }}</b></span>
    </td>
    <td class="row-lines row-approaches">
        @foreach ($item->approachList() as $approach)
            <span>{{ $approach->label() }}<b>{{ $item->{$approach->column()} !== null ? Format::money($item->{$approach->column()}) : '—' }}</b></span>
        @endforeach
        <span>{{ __('Reconciliation step') }}<b>{{ $item->market_value !== null ? Format::money($item->market_value) : '—' }}</b></span>
    </td>
    <td class="row-lines">
        <span>{{ __('Architectural style (facade)') }}: <i class="badge badge-info">{{ $rv['architecture'] }}</i></span>
        <span>{{ __('Special amenities') }}:
            @forelse ($features as $feature)<i class="badge badge-info">{{ $feature->label() }}</i>@empty<i class="badge badge-info">{{ __('No special amenities') }}</i>@endforelse
        </span>
        <span>{{ __('Repair condition') }}: {{ $rv['repair'] ?? '—' }}</span>
        <span>{{ __('Construction completed (%)') }}: <i class="badge badge-info">{{ Format::number((float) $rv['completion']) }}</i></span>
    </td>
    <td class="t-center">
        <a class="btn btn-sm btn-success" href="{{ route('admin.real-estates.report.edit', $item->id) }}#step-inspection"><x-icon name="table" :size="14" /> {{ __('Act') }}</a>
    </td>
    <td class="row-lines row-status">
        <x-status :status="$item->status" />
        <span class="row-date">{{ Format::datetime($item->completed_at ?? $item->updated_at) }}</span>
        <span>{{ __('Number of buildings') }}: <i class="badge badge-info">{{ $buildings }}</i></span>
        <b>{{ $item->timeSpent() }}</b>
    </td>
    <td class="row-lines row-persons">
        @foreach ($item->clients as $person)
            <span>{{ PersonRole::labelFor($person->pivot->role) }}: <b>{{ $person->title }}</b></span>
        @endforeach
        @if ($item->creator && $item->creator->id !== $item->appraiser_id)
            <span>{{ __('Assistant of the appraiser') }}: <b>{{ $item->creator->name }}</b></span>
        @endif
        @if ($item->appraiser)
            <span>{{ __('Appraiser') }}: <b>{{ $item->appraiser->name }}</b></span>
        @endif
        @if ($item->clients->isEmpty() && ! $item->appraiser && ! $item->creator)<span class="muted">—</span>@endif
    </td>
    <td class="row-sign">@if ($byAppraiser)<b>{{ __('Confirmed by the appraiser') }}</b>@else<span class="muted">{{ __('Not confirmed') }}</span>@endif</td>
    <td class="row-sign">@if ($byDirector)<b>{{ __('Confirmed by the director') }}</b>@else<span class="muted">{{ __('Not confirmed') }}</span>@endif</td>
    <td class="row-buttons">
        <a class="btn btn-sm btn-info btn-icon" href="{{ $show }}#documents-section" title="{{ __('Photos and documents') }}" aria-label="{{ __('Photos and documents') }}"><x-icon name="image" :size="15" /></a>
        @can('edit-valuation', $item)
            <a class="btn btn-sm btn-warning btn-icon" href="{{ $show }}" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}"><x-icon name="square-pen" :size="15" /></a>
        @endcan
    </td>
</tr>
