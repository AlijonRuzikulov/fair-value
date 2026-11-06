{{-- "Baholangan qiymati" of the portal's lists: the value, the contract sum with VAT, the paid amount and whether it is paid. --}}
@php
    use App\Support\Format;
    $vatRate = (float) \App\Models\Setting::get('vat_rate');
@endphp
<b>{{ Format::money($item->market_value ?? 0) }}</b>
@if ($item->service_fee !== null)
    <b>{{ Format::money($item->service_fee) }}</b>
    <em>{{ __('Contract sum + VAT (:rate%)', ['rate' => Format::number($vatRate)]) }}: {{ Format::money((float) $item->service_fee * (1 + $vatRate / 100)) }}</em>
    <em>{{ __('Paid amount') }}: {{ Format::money($item->paid_amount ?? 0) }}</em>
    @include('admin.objects._portal-paid')
@endif
