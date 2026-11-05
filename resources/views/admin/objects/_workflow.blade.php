{{-- Approval of the valuation: who submitted, approved and accepted it, what can be done now, and the history with the time spent. --}}
@php
    use App\Enums\ValuationStatus;
    use App\Models\ValuationStep;
    use App\Support\Format;
    $status = $item->status;
    $submitted = $item->currentStep('submitted');
    $approved = $item->currentStep('approved');
    $accepted = $item->currentStep('accepted');
    $route = route($module->routeName('workflow'), $item->id);
    $canFinalize = auth()->user()->can('finalize-valuations');
    $canApprove = auth()->user()->can('approve-valuations');
    $stages = [
        [__('The appraiser submitted the report'), $submitted, $item->appraiser?->name],
        [__('The director approved the report'), $approved, null],
        [__('The customer signed the act'), $accepted, null],
    ];
    // Completed on the form, without the approval steps.
    $completedDirectly = ! $approved && $status === ValuationStatus::Completed;
    // Steps that need a reason: returning, reopening, cancelling.
    $withReason = $canFinalize ? array_filter([
        'returned' => $status === ValuationStatus::Review && $canApprove ? [__('Return for correction'), 'undo-2', ''] : null,
        'reopened' => $status === ValuationStatus::Completed && $canApprove ? [__('Revaluate'), 'refresh-cw', ''] : null,
        'cancelled' => ! in_array($status, [ValuationStatus::Completed, ValuationStatus::Cancelled], true) ? [__('Cancel the valuation'), 'circle-x', 'btn-danger'] : null,
    ]) : [];
@endphp
<x-card :title="__('Status and approval')" icon="badge-check">
    <x-slot:actions><x-status :status="$status" /></x-slot:actions>

    @include('admin.objects._workflow-stages')
    @include('admin.objects._workflow-actions')

    @if ($withReason)
        <details class="approval-more">
            <summary>{{ __('Return, revaluate or cancel') }}</summary>
            @foreach ($withReason as $action => [$label, $icon, $class])
                <form method="post" action="{{ $route }}" class="approval-reason">
                    @csrf <input type="hidden" name="action" value="{{ $action }}">
                    <input class="input input-sm" name="comment" required maxlength="2000" placeholder="{{ __('Reason') }}" aria-label="{{ $label }}: {{ __('Reason') }}">
                    <button class="btn btn-sm {{ $class }}" type="submit"><x-icon :name="$icon" :size="14" /> {{ $label }}</button>
                </form>
            @endforeach
        </details>
    @endif

    <div class="approval-history">
        <div class="approval-history-head">
            <strong>{{ __('History') }}</strong>
            <span class="muted">{{ __('Time spent') }}: {{ $item->timeSpent() }}</span>
        </div>
        @include('admin.objects._workflow-history')
    </div>
</x-card>
