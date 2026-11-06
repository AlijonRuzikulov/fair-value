{{-- The status in the words and colours of the valuation portal. --}}
@php
    use App\Enums\ValuationStatus;
    $status = $item->status ?? ValuationStatus::New;
    // A new valuation is "assigned to the appraiser" where the portal says so (vehicles and damage).
    [$text, $tone] = match ($status) {
        ValuationStatus::New => ($assigned ?? false) && $item->appraiser_id ? [__('Assigned to the appraiser'), 'dark'] : [__('Not reviewed yet'), 'danger'],
        ValuationStatus::InProgress => [__('Valuation in progress'), 'warning'],
        ValuationStatus::Review => [$status->label(), 'brand'],
        ValuationStatus::Completed => [__('Confirmed'), 'success'],
        ValuationStatus::Cancelled => [__('Rejected'), 'muted'],
    };
@endphp
<span class="badge badge-{{ $tone }}">{{ $text }}</span>
