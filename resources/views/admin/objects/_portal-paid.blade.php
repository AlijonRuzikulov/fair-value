{{-- Whether the contract is paid: "To'lanmagan", partly or fully paid. --}}
@php
    $fee = (float) $item->service_fee;
    $paid = (float) $item->paid_amount;
    [$text, $tone] = match (true) {
        $fee > 0 && $paid >= $fee => [__('Paid'), 'success'],
        $paid > 0 => [__('Partly paid'), 'warning'],
        default => [__('Not paid'), 'danger'],
    };
@endphp
<span class="badge badge-{{ $tone }} row-paid">{{ $text }}</span>
