{{-- "Kontrakt summasi" of the valuation portal: the sum, the sum with VAT and the paid amount under it. --}}
@php
    use App\Support\Format;
    $vatRate = (float) \App\Models\Setting::get('vat_rate');
    $money = fn ($v) => $v !== null && $v !== '' ? Format::number($v, fmod((float) $v, 1.0) ? 2 : 0) : '';
@endphp
<div class="field span-12 contract-sum" data-vat="{{ $vatRate }}">
    <label for="f-service_fee">{{ __('Contract sum') }}</label>
    <input id="f-service_fee" name="service_fee" type="text" inputmode="decimal" data-money autocomplete="off" @class(['input', 'is-invalid' => $errors->has('service_fee')])
           value="{{ old('service_fee', $money($item->service_fee)) }}" data-contract-sum>
    @error('service_fee')<p class="field-error">{{ $message }}</p>@enderror
    <p class="contract-line">{{ __('With VAT') }}: <span data-contract-vat>{{ $item->service_fee !== null ? Format::number((float) $item->service_fee * (1 + $vatRate / 100)) : '' }}</span> {{ __('sum') }}</p>
    <p class="contract-line">
        <label for="f-paid_amount">{{ __('Paid amount') }}:</label>
        <input id="f-paid_amount" name="paid_amount" type="text" inputmode="decimal" data-money autocomplete="off" class="input-plain" value="{{ old('paid_amount', $money($item->paid_amount)) }}" size="12">
        {{ __('sum') }}
    </p>
</div>
