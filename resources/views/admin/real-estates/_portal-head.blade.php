{{-- Head of the real estate page in the classic look: the title and the four steps of the valuation portal. --}}
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
    $kindLabel = ($item->kind ?? \App\Enums\RealEstateKind::NonResidential)->label();
@endphp
<section class="card portal-head">
    <div class="portal-head-top">
        <h2>{{ __('Enter the real estate data (:kind)', ['kind' => $kindLabel]) }}</h2>
        <div class="portal-head-actions">
            @foreach ($headLinks ?? [] as [$label, $url, $class, $icon])
                <a class="btn {{ $class }}" href="{{ $url }}"><x-icon :name="$icon" :size="16" /> {{ $label }}</a>
            @endforeach
        </div>
    </div>
    <ol class="portal-steps" aria-label="{{ __('Steps') }}">
        @foreach ($done as $label => $ok)
            <li @class(['is-done' => $ok, 'is-current' => $loop->index === $current]) @if ($loop->index === $current) aria-current="step" @endif>
                <span class="portal-step-num">@if ($ok)<x-icon name="check" :size="14" />@else{{ $loop->iteration }}@endif</span>
                <span class="portal-step-label">{{ $label }}</span>
            </li>
        @endforeach
    </ol>
    @if ($item->status === ValuationStatus::Cancelled)
        <p class="portal-head-note"><x-status :status="$item->status" /></p>
    @endif
</section>
