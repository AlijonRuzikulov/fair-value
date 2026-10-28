@php use App\Enums\ValuationStatus; @endphp
@if ($canFinalize)
    <div class="approval-actions">
        @if (in_array($status, [ValuationStatus::New, ValuationStatus::InProgress], true))
            <form method="post" action="{{ $route }}">
                @csrf <input type="hidden" name="action" value="submitted">
                <button class="btn btn-primary btn-sm" type="submit"><x-icon name="send-horizontal" :size="14" /> {{ __('Submit for approval') }}</button>
            </form>
        @endif
        @if ($status === ValuationStatus::Review && $canApprove)
            <form method="post" action="{{ $route }}">
                @csrf <input type="hidden" name="action" value="approved">
                <button class="btn btn-primary btn-sm" type="submit"><x-icon name="badge-check" :size="14" /> {{ __('Approve') }}</button>
            </form>
        @endif
        @if ($status === ValuationStatus::Completed && ! $accepted)
            <form method="post" action="{{ $route }}">
                @csrf <input type="hidden" name="action" value="accepted">
                <button class="btn btn-sm" type="submit"><x-icon name="handshake" :size="14" /> {{ __('The act is signed') }}</button>
            </form>
        @endif
    </div>
    @if ($status === ValuationStatus::Review && ! $canApprove)
        <p class="field-hint">{{ __('The report is waiting for the director (an administrator) to approve it.') }}</p>
    @endif
@endif
