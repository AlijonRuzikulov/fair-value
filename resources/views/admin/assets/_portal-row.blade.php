{{-- A row of the portal's list of mixed property. --}}
@php
    use App\Enums\ValuationStatus;
    use App\Support\Format;
    $show = route($module->routeName('show'), $item->id);
    $owner = $item->owner();
    $place = collect([$item->region?->name ?? $owner?->region?->name, $item->region ? $item->district?->name : $owner?->district?->name])->filter()->implode(' ');
@endphp
<tr class="portal-row">
    <td class="row-id">{{ $item->id }}</td>
    <td class="row-object">
        <a class="cell-title" href="{{ $show }}">{{ $item->name }}</a>
        <em>{{ __('Contract sum') }}: {{ Format::money($item->service_fee ?? 0) }}</em>
        <em>{{ __('Paid amount') }}: {{ Format::money($item->paid_amount ?? 0) }}</em>
        @include('admin.objects._portal-paid')
    </td>
    <td class="row-owners">@include('admin.objects._portal-owners')</td>
    <td class="row-place">{{ $place }}</td>
    <td class="t-right row-sum">{{ Format::money($item->service_fee ?? 0) }}</td>
    <td class="t-right row-sum">{{ Format::money($item->market_value ?? 0) }}</td>
    <td class="row-status">
        @include('admin.objects._portal-badge')
        @if ($item->status !== ValuationStatus::New)<span class="row-date">{{ ($item->completed_at ?? $item->updated_at)?->format('d/m/Y H:i') }}</span>@endif
    </td>
    <td class="nowrap">{{ $item->created_at?->format('d/m/Y') }}</td>
    <td class="row-creator">{{ $item->creator?->name ?? '—' }}</td>
    <td class="row-buttons">
        <a class="btn btn-sm btn-info btn-icon" href="{{ $show }}#documents" title="{{ __('Photos and documents') }}" aria-label="{{ __('Photos and documents') }}"><x-icon name="image" :size="15" /></a>
        @can('edit-valuation', $item)
            <a class="btn btn-sm btn-warning btn-icon" href="{{ $show }}" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}"><x-icon name="square-pen" :size="15" /></a>
        @endcan
    </td>
</tr>
