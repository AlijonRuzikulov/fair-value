@php use App\Support\Format; @endphp
<x-card :title="__('Damage calculation')" icon="calculator" flush>
    @if ($item->items->isEmpty())
        <x-empty icon="calculator" :title="__('No calculation lines yet')" />
    @else
        <div class="table-wrap">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Name') }}</th>
                        <th class="t-right">{{ __('Qty') }}</th>
                        <th class="t-right">{{ __('Norm-h') }}</th>
                        <th class="t-right">{{ __('Price / rate') }}</th>
                        <th class="t-right">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($item->items as $line)
                        <tr>
                            <td><span class="chip">{{ $line->type->label() }}</span></td>
                            <td>{{ $line->name }}@if ($line->operation)<span class="cell-sub">{{ $line->operationLabel() }}</span>@endif</td>
                            <td class="t-right num">{{ Format::number($line->quantity, fmod((float) $line->quantity, 1.0) ? 2 : 0) }}</td>
                            <td class="t-right num">{{ $line->hours !== null ? Format::number($line->hours, 2) : '—' }}</td>
                            <td class="t-right num">{{ Format::number($line->unit_price) }}</td>
                            <td class="t-right cell-num">{{ Format::number($line->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    <div class="card-body">
        <div class="totals" style="max-width: 420px; margin-left: auto">
            <div class="totals-row"><span>{{ __('Spare parts') }}</span><strong>{{ Format::money($item->parts_total) }}</strong></div>
            <div class="totals-row minus"><span>{{ __('Wear') }} ({{ Format::number($item->wear_percent, 2) }}%)</span><strong>−{{ Format::money($item->wear_total) }}</strong></div>
            <div class="totals-row"><span>{{ __('Small parts') }}</span><strong>{{ Format::money($item->small_parts_total) }}</strong></div>
            <div class="totals-row"><span>{{ __('Repair work') }}</span><strong>{{ Format::money($item->labor_total) }}</strong></div>
            <div class="totals-row"><span>{{ __('Paint materials') }}</span><strong>{{ Format::money($item->paint_total) }}</strong></div>
            <div class="totals-row"><span>{{ __('Other expenses') }}</span><strong>{{ Format::money($item->other_total) }}</strong></div>
            <div class="totals-row"><span>{{ __('Loss of commodity value') }}</span><strong>{{ Format::money($item->commodity_loss) }}</strong></div>
            <div class="totals-grand"><span>{{ __('Total damage') }}</span><span>{{ Format::money($item->market_value) }}</span></div>
        </div>
    </div>
</x-card>
