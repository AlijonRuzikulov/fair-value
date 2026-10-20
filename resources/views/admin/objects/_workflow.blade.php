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
@endphp
<x-card :title="__('Status and approval')" icon="badge-check">
    <x-slot:actions><x-status :status="$status" /></x-slot:actions>

    <ol class="approval">
        @foreach ($stages as [$title, $step, $who])
            @php $done = $step || ($loop->index === 1 && $completedDirectly); @endphp
            <li @class(['is-done' => $done])>
                <span class="approval-mark"><x-icon :name="$done ? 'check' : 'hourglass'" :size="14" /></span>
                <div>
                    <strong>{{ $title }}</strong>
                    <small>@if ($step){{ Format::datetime($step->created_at) }} · {{ $step->user?->name ?? '—' }}@elseif ($done){{ __('Completed without approval') }} · {{ Format::datetime($item->completed_at) }}@else{{ __('Not yet') }}@if ($who) · {{ $who }}@endif @endif</small>
                </div>
            </li>
        @endforeach
    </ol>

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

        {{-- Steps that need a reason: returning, reopening, cancelling. --}}
        @php
            $withReason = array_filter([
                'returned' => $status === ValuationStatus::Review && $canApprove ? [__('Return for correction'), 'undo-2', ''] : null,
                'reopened' => $status === ValuationStatus::Completed && $canApprove ? [__('Revaluate'), 'refresh-cw', ''] : null,
                'cancelled' => ! in_array($status, [ValuationStatus::Completed, ValuationStatus::Cancelled], true) ? [__('Cancel the valuation'), 'circle-x', 'btn-danger'] : null,
            ]);
        @endphp
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
        @if ($status === ValuationStatus::Review && ! $canApprove)
            <p class="field-hint">{{ __('The report is waiting for the director (an administrator) to approve it.') }}</p>
        @endif
    @endif

    <div class="approval-history">
        <div class="approval-history-head">
            <strong>{{ __('History') }}</strong>
            <span class="muted">{{ __('Time spent') }}: {{ $item->timeSpent() }}</span>
        </div>
        @if ($item->steps->isEmpty())
            <p class="muted">{{ __('No approval steps yet.') }}</p>
        @else
            <table class="table table-compact">
                <tbody>
                    @foreach ($item->steps->reverse() as $step)
                        <tr>
                            <td class="nowrap">{{ Format::datetime($step->created_at) }}</td>
                            <td>{{ $step->user?->name ?? '—' }}</td>
                            <td><strong>{{ ValuationStep::label($step->action) }}</strong>@if ($step->comment)<span class="cell-sub">{{ $step->comment }}</span>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-card>
