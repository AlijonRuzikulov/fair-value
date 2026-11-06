{{-- A row of the portal's list of cars whose damage is valued. --}}
@php
    $show = route($module->routeName('show'), $item->id);
    $purpose = $item->purpose ? \App\Enums\ValuationPurpose::tryFrom($item->purpose) : null;
    $name = trim(implode(' ', array_filter([$item->vehicle_name, $item->year_made ? __(':year - year', ['year' => $item->year_made]) : null])));
@endphp
<tr class="portal-row portal-row-center">
    <td class="row-id">{{ $item->id }}@if ($purpose)<span>{{ $purpose->shortLabel() }}</span>@endif</td>
    <td class="row-owners">@include('admin.objects._portal-owners')</td>
    <td class="row-name"><a href="{{ $show }}">{{ $name ?: '—' }}</a></td>
    <td class="row-value">@include('admin.objects._portal-value')</td>
    <td class="row-status">@include('admin.objects._portal-badge', ['assigned' => true])</td>
    <td class="row-dates">@include('admin.objects._portal-dates')</td>
    <td class="row-creator"><span>{{ $item->creator?->name }}</span>@if ($item->appraiser)<span class="row-appraiser">{{ $item->appraiser->name }}</span>@endif</td>
    <td class="row-buttons">
        @can('edit-valuation', $item)
            <a class="btn btn-sm btn-warning btn-icon" href="{{ $show }}" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}"><x-icon name="square-pen" :size="15" /></a>
        @endcan
        @if ($item->status === \App\Enums\ValuationStatus::Completed)
            <a class="btn btn-sm btn-fill-danger btn-icon" href="{{ $item->verifyUrl() }}" target="_blank" data-share title="{{ __('Share') }}" aria-label="{{ __('Share') }}"><x-icon name="share-2" :size="15" /></a>
        @endif
    </td>
</tr>
