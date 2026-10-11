@php
    use App\Support\Format;
    $rows = old('items', $lines->map(fn ($l) => [
        'type' => $l->type->value, 'name' => $l->name, 'operation' => $l->operation,
        'quantity' => (float) $l->quantity, 'hours' => $l->hours !== null ? (float) $l->hours : null, 'unit_price' => (float) $l->unit_price,
    ])->all());
@endphp
<section class="card" data-damage-calc
    data-parts-url="{{ route('admin.spare-parts.lookup') }}"
    data-small-url="{{ route('admin.small-parts.lookup') }}"
    data-norms-url="{{ route('admin.repair-norms.lookup') }}">
    <header class="card-head">
        <h2 class="card-title"><x-icon name="calculator" />{{ __('Damage calculation') }}</h2>
    </header>
    <div class="card-body">
        <div class="form-grid">
            <x-field name="wear_percent" :label="__('Vehicle wear')" :value="$item->wear_percent" money suffix="%" span="3" required data-calc-wear :hint="__('Applied to spare parts only.')" />
            <x-field name="labor_rate" :label="__('Standard hour rate')" :value="$item->labor_rate" money :suffix="__('sum')" span="3" required data-calc-rate />
            <x-field name="commodity_loss" :label="__('Loss of commodity value')" :value="$item->commodity_loss" money :suffix="__('sum')" span="3" data-calc-uts />
        </div>
    </div>

    <div class="table-wrap">
        <table class="table calc-table">
            <thead>
                <tr>
                    <th class="col-type">{{ __('Type') }}</th>
                    <th>{{ __('Name') }}</th>
                    <th class="col-op">{{ __('Operation') }}</th>
                    <th class="col-qty">{{ __('Qty') }}</th>
                    <th class="col-hours">{{ __('Norm-h') }}</th>
                    <th class="col-price">{{ __('Price / rate') }}</th>
                    <th class="col-amount">{{ __('Amount') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody data-calc-rows>
                @foreach ($rows as $i => $row)
                    @include('admin.damage-cases._line', ['i' => $i, 'row' => $row])
                @endforeach
            </tbody>
        </table>
    </div>
    <template data-calc-template>
        @include('admin.damage-cases._line', ['i' => '__i__', 'row' => ['type' => 'part', 'name' => '', 'operation' => null, 'quantity' => 1, 'hours' => null, 'unit_price' => null]])
    </template>

    <div class="calc-toolbar">
        <div class="catalog-picker combo" data-catalog-picker>
            <input type="search" class="input" placeholder="{{ __('Find a spare part, small part or repair norm…') }}" data-catalog-input autocomplete="off">
        </div>
        <button type="button" class="btn" data-calc-add="part"><x-icon name="plus" :size="16" /> {{ __('Spare part') }}</button>
        <button type="button" class="btn" data-calc-add="labor"><x-icon name="plus" :size="16" /> {{ __('Work') }}</button>
        <button type="button" class="btn" data-calc-add="paint"><x-icon name="plus" :size="16" /> {{ __('Paint') }}</button>
        <button type="button" class="btn btn-ghost" data-calc-add="other"><x-icon name="plus" :size="16" /> {{ __('Other') }}</button>
    </div>

    <div class="card-foot">
        <div class="grid grid-2">
            <p class="field-hint">{{ __('Total = spare parts − wear + small parts + repair work + paint + other + loss of commodity value.') }}</p>
            <div class="totals" data-calc-totals>
                <div class="totals-row"><span>{{ __('Spare parts') }}</span><strong data-total="part">0</strong></div>
                <div class="totals-row minus"><span>{{ __('Wear') }}</span><strong data-total="wear">0</strong></div>
                <div class="totals-row"><span>{{ __('Small parts') }}</span><strong data-total="small_part">0</strong></div>
                <div class="totals-row"><span>{{ __('Repair work') }}</span><strong data-total="labor">0</strong></div>
                <div class="totals-row"><span>{{ __('Paint materials') }}</span><strong data-total="paint">0</strong></div>
                <div class="totals-row"><span>{{ __('Other expenses') }}</span><strong data-total="other">0</strong></div>
                <div class="totals-row"><span>{{ __('Loss of commodity value') }}</span><strong data-total="uts">0</strong></div>
                <div class="totals-grand"><span>{{ __('Total damage') }}</span><span data-total="grand">0</span></div>
            </div>
        </div>
    </div>
</section>
