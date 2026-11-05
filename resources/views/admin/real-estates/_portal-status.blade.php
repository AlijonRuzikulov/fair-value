{{-- "Holat" and "Tarixi" of the valuation portal: the four confirmations, the history and the time spent. --}}
@php
    use App\Enums\ValuationStatus;
    use App\Models\ValuationStep;
    use App\Support\Format;
    $exists = $item->exists;
    $status = $item->status ?? ValuationStatus::New;
    $step = fn (string $action) => $exists ? $item->currentStep($action) : null;
    $approved = $step('approved');
    $rows = [
        [__('Report by the appraiser'), $step('submitted')],
        [__('Invoice by the director'), $step('invoiced')],
        [__('Report by the director'), $approved ?? ($status === ValuationStatus::Completed ? true : null)],
        [__('Invoice by the customer'), $step('accepted')],
    ];
    $statusLine = match ($status) {
        ValuationStatus::New, ValuationStatus::InProgress => __('Not reviewed yet'),
        ValuationStatus::Review => __('Under review'),
        ValuationStatus::Completed => __('Approved'),
        ValuationStatus::Cancelled => __('Cancelled'),
    };
    $steps = $exists ? $item->steps : collect();
    // For the buttons of the steps.
    $route = $exists ? route($module->routeName('workflow'), $item->id) : null;
    $canFinalize = auth()->user()->can('finalize-valuations');
    $canApprove = auth()->user()->can('approve-valuations');
    $accepted = $step('accepted');
@endphp
<div class="portal-cards">
    <section class="card card-accent" id="status">
        <header class="card-head"><h2 class="card-title">{{ __('Status of the valuation') }}</h2></header>
        <div class="card-body">
            <p class="sign-status"><x-status :status="$status" /> {{ $statusLine }}</p>
            <ul class="sign-rows">
                @foreach ($rows as [$label, $done])
                    <li @class(['is-done' => (bool) $done])>
                        <span>{{ $label }}</span>
                        <span class="sign-state">
                            @if ($done)
                                <span class="badge badge-success">{{ __('Confirmed') }}</span>
                                @if ($done instanceof ValuationStep)<small>{{ Format::datetime($done->created_at) }} · {{ $done->user?->name ?? '—' }}</small>@endif
                            @else
                                <span class="badge badge-muted">{{ __('Not confirmed') }}</span>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
            @if ($exists)
                @include('admin.objects._workflow-actions')
            @endif
        </div>
    </section>
    <section class="card card-accent" id="history">
        <header class="card-head"><h2 class="card-title">{{ __('History of the valuation') }}</h2></header>
        <div class="card-body">
            <p class="sign-status">{{ __('Time spent') }}: <strong>{{ $exists ? $item->timeSpent() : '—' }}</strong></p>
            <div class="table-wrap">
                <table class="table table-compact">
                    <thead><tr><th>№</th><th>{{ __('Date') }}</th><th>{{ __('By') }}</th><th>{{ __('Step of the history') }}</th></tr></thead>
                    <tbody>
                        @forelse ($steps as $historyStep)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td class="nowrap">{{ Format::datetime($historyStep->created_at) }}</td>
                                <td>{{ $historyStep->user?->name ?? '—' }}</td>
                                <td><strong>{{ ValuationStep::label($historyStep->action) }}</strong>@if ($historyStep->comment)<span class="cell-sub">{{ $historyStep->comment }}</span>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="muted">{{ __('No approval steps yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
