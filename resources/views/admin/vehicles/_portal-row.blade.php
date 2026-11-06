{{-- A row of the portal's list of vehicles. --}}
@php
    $show = route($module->routeName('show'), $item->id);
    $purpose = $item->purpose ? \App\Enums\ValuationPurpose::tryFrom($item->purpose) : null;
@endphp
<tr class="portal-row portal-row-center">
    <td class="row-id">{{ $item->id }}@if ($purpose)<span>{{ $purpose->shortLabel() }}</span>@endif</td>
    <td class="row-owners">@include('admin.objects._portal-owners')</td>
    <td class="row-name"><a href="{{ $show }}">{{ $item->portalName() }}</a></td>
    <td><a class="btn btn-sm btn-muted" href="{{ $show }}#object-data"><x-icon name="table" :size="14" /> {{ __('Act') }}</a></td>
    <td class="row-value">@include('admin.objects._portal-value')</td>
    <td class="row-status">@include('admin.objects._portal-badge', ['assigned' => true])</td>
    <td class="row-dates">@include('admin.objects._portal-dates')</td>
    <td class="row-creator"><span>{{ $item->creator?->name }}</span>@if ($item->appraiser)<span class="row-appraiser">{{ $item->appraiser->name }}</span>@endif</td>
    @include('admin.objects._portal-signs')
    <td class="row-buttons">
        @can('edit-valuation', $item)
            <a class="btn btn-sm btn-warning btn-icon" href="{{ $show }}" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}"><x-icon name="square-pen" :size="15" /></a>
        @endcan
    </td>
</tr>
