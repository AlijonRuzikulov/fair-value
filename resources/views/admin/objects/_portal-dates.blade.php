{{-- "Kiritilgan sana" of the portal: entered (blue), approved (orange) and the time it took, red while it goes on. --}}
@php
    use App\Enums\ValuationStatus;
    $approvedAt = $item->currentStep('approved')?->created_at ?? ($item->status === ValuationStatus::Completed ? $item->completed_at : null);
@endphp
<span class="row-date">{{ $item->created_at?->format('d/m/Y H:i') }}</span>
@if ($approvedAt)<span class="row-date-end">{{ $approvedAt->format('d/m/Y H:i') }}</span>@endif
<b @class(['row-took', 'is-running' => ! $approvedAt])>{{ $item->timeSpent(3) }}</b>
