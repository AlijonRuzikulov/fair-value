{{-- The four steps of the valuation portal over the real estate page: done green, the current one in the colour of the menu. --}}
@php
    use App\Enums\ValuationStatus;
    $applied = $item->exists ? $item->approachList() : [];
    $done = [
        __('Saving the main data of the real estate') => $item->exists && $item->total_area > 0 && $item->region_id !== null,
        __('Calculating the approaches') => $applied !== [] && collect($applied)->every(fn ($a) => $item->{$a->column()} !== null),
        __('Reconciliation and approval') => $item->market_value !== null && in_array($item->status, [ValuationStatus::Review, ValuationStatus::Completed], true),
        __('The end') => $item->status === ValuationStatus::Completed,
    ];
    $current = array_search(false, array_values($done), true);
@endphp
<ol class="portal-steps" aria-label="{{ __('Steps') }}">
    @foreach ($done as $label => $ok)
        <li @class(['is-done' => $ok, 'is-current' => $loop->index === $current]) @if ($loop->index === $current) aria-current="step" @endif>
            <span class="portal-step-num">@if ($ok)<x-icon name="check" :size="14" />@else{{ $loop->iteration }}@endif</span>
            <span class="portal-step-label">{{ $label }}</span>
        </li>
    @endforeach
</ol>
