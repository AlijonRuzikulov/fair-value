{{-- The body of an approach on the real estate page: its data and value, or the reason it was refused; applied or refused with a button. --}}
@php
    use App\Enums\ValuationApproach;
    $isApplied = in_array($approach, $applied, true);
@endphp
@if ($isApplied)
    @if ($approach === ValuationApproach::Comparative)
        @if ($analogType)
            @include('admin.objects._analogs', ['analogType' => $analogType, 'aiAvailable' => app(\App\Services\Analogs\ListingAi::class)->available()])
        @endif
    @else
        @include('admin.real-estates._approaches', ['only' => $approach->value])
    @endif
    @if ($canEdit)
        <form method="post" action="{{ route('admin.real-estates.approaches.toggle', [$item->id, $approach->value]) }}" class="portal-refuse">
            @csrf @method('put')
            <input type="hidden" name="applied" value="0">
            <input class="input input-sm" name="reason" maxlength="2000" placeholder="{{ __('Why the approach is not applied (printed in the report)') }}" aria-label="{{ __('Reason') }}">
            <button class="btn btn-sm btn-danger" type="submit"><x-icon name="circle-x" :size="14" /> {{ __('Refuse the approach') }}</button>
        </form>
    @endif
@else
    <p class="portal-reason-text">{{ $reportValues['reason_'.$approach->value] }}</p>
    @if ($canEdit)
        <form method="post" action="{{ route('admin.real-estates.approaches.toggle', [$item->id, $approach->value]) }}">
            @csrf @method('put')
            <input type="hidden" name="applied" value="1">
            <button class="btn btn-sm btn-success" type="submit"><x-icon name="check" :size="14" /> {{ __('Apply the approach') }}</button>
        </form>
    @endif
    {{-- Analogues can be gathered before the approach is applied. --}}
    @if ($approach === ValuationApproach::Comparative && $analogType)
        @include('admin.objects._analogs', ['analogType' => $analogType, 'aiAvailable' => app(\App\Services\Analogs\ListingAi::class)->available()])
    @endif
@endif
