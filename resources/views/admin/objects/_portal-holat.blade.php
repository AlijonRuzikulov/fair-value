{{-- "Holat" of the valuation portal: the status and the four confirmations (with the steps' buttons once the object is saved). --}}
@php
    use App\Enums\ValuationStatus;
    use App\Models\ValuationStep;
    use App\Support\Format;
    $exists = $item->exists;
    $status = $item->status ?? ValuationStatus::New;
    $step = fn (string $action) => $exists ? $item->currentStep($action) : null;
    $rows = [
        [__('Report by the appraiser'), $step('submitted')],
        [__('Invoice by the director'), $step('invoiced')],
        [__('Report by the director'), $step('approved') ?? ($status === ValuationStatus::Completed ? true : null)],
        [__('Invoice by the customer'), $step('accepted')],
    ];
    $route = $exists ? route($module->routeName('workflow'), $item->id) : null;
    $canFinalize = auth()->user()->can('finalize-valuations');
    $canApprove = auth()->user()->can('approve-valuations');
    $accepted = $step('accepted');
@endphp
<div class="portal-cards portal-cards-half">
    <section class="card card-accent" id="status">
        <header class="card-head"><h2 class="card-title">{{ __('Status of the valuation') }}</h2></header>
        <div class="card-body sign-body">
            <div class="sign-status">
                @include('admin.objects._portal-badge')
                @if ($exists && $status !== ValuationStatus::New)<span>{{ __('Date') }}: {{ Format::datetime($item->completed_at ?? $item->updated_at) }}</span>@endif
            </div>
            <ul class="sign-rows">
                @foreach ($rows as [$label, $done])
                    <li>
                        {{ $label }}
                        @if ($done)
                            <span class="badge badge-info" @if ($done instanceof ValuationStep) title="{{ Format::datetime($done->created_at) }} · {{ $done->user?->name }}" @endif>{{ __('Confirmed') }}</span>
                        @else
                            <span class="badge badge-warning">{{ __('Not confirmed') }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
            @if ($exists)
                @include('admin.objects._workflow-actions')
            @endif
        </div>
    </section>
</div>
