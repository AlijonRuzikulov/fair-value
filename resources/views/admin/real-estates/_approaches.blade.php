{{-- Cost and income approaches of the object, when they are applied: the data and the value worked out from it ($only: just one of them). --}}
@php
    use App\Enums\ValuationApproach;
    use App\Support\CostApproach;
    use App\Support\Format;
    use App\Support\IncomeApproach;
    $applied = $item->approachList();
    $report = (array) $item->report;
    $canEdit = \Illuminate\Support\Facades\Gate::allows('edit-valuation', $item);
    $num = fn ($v, $d = 2) => $v === null || $v === '' ? '' : rtrim(rtrim(number_format((float) $v, $d, ',', ' '), '0'), ',');
@endphp

@if (in_array(ValuationApproach::Cost, $applied, true) && ($only ?? 'cost') === 'cost')
    @php
        $costInput = $report['cost'] ?? ['buildings' => [[
            'name' => $item->name, 'unit' => 'm2', 'quantity' => $item->total_area, 'rate' => null, 'year_built' => $item->year_built,
        ]], 'profit' => 10, 'land' => 0];
        $cost = CostApproach::calculate($costInput, (int) ($item->valuation_date ?? now())->format('Y'));
        $buildings = array_values((array) ($costInput['buildings'] ?? []));
    @endphp
    <x-card :title="__('Cost approach')" icon="hammer" id="cost-approach">
        <x-slot:actions>@if ($cost['value'])<span class="badge badge-brand">{{ Format::money($cost['value']) }}</span>@endif</x-slot:actions>
        <form method="post" action="{{ route('admin.real-estates.approaches.cost', $item->id) }}">
            @csrf @method('put')
            <p class="field-hint" style="margin-bottom: 10px">{{ __('Buildings to be valued: the cost of replacing each one (area or volume × cost per unit, with the developer\'s profit), less physical wear (by age and economic life, or as assessed), functional and external obsolescence; plus the value of the land.') }}</p>
            <div class="table-wrap">
                <table class="table cost-table" data-rows>
                    <thead>
                        <tr>
                            <th>{{ __('Building') }}</th><th>{{ __('Area or volume') }}</th><th>{{ __('Unit') }}</th><th>{{ __('Replacement cost per unit') }}</th>
                            <th>{{ __('Year built') }}</th><th>{{ __('Life, years') }}</th><th>{{ __('Physical wear, %') }}</th><th>{{ __('Functional, %') }}</th><th>{{ __('External, %') }}</th>
                            <th title="{{ __('Light structural element: economic life 40 years') }}">{{ __('Light') }}</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ([...$buildings, ...($canEdit ? [[]] : [])] as $i => $b)
                            <tr data-row>
                                <td><input class="input" name="buildings[{{ $i }}][name]" value="{{ $b['name'] ?? '' }}" placeholder="{{ __('e.g. Main building') }}" @disabled(! $canEdit)></td>
                                <td><input class="input w-90" name="buildings[{{ $i }}][quantity]" value="{{ $num($b['quantity'] ?? null) }}" inputmode="decimal" @disabled(! $canEdit)></td>
                                <td><select class="input w-80" name="buildings[{{ $i }}][unit]" @disabled(! $canEdit)><option value="m2" @selected(($b['unit'] ?? 'm2') === 'm2')>{{ __('m²') }}</option><option value="m3" @selected(($b['unit'] ?? '') === 'm3')>{{ __('m³') }}</option></select></td>
                                <td><input class="input w-140" name="buildings[{{ $i }}][rate]" value="{{ $num($b['rate'] ?? null) }}" inputmode="decimal" @disabled(! $canEdit)></td>
                                <td><input class="input w-80" name="buildings[{{ $i }}][year_built]" value="{{ $b['year_built'] ?? '' }}" inputmode="numeric" @disabled(! $canEdit)></td>
                                <td><input class="input w-70" name="buildings[{{ $i }}][life]" value="{{ $b['life'] ?? '' }}" inputmode="numeric" placeholder="{{ ($b['light'] ?? false) ? CostApproach::LIGHT_LIFE : CostApproach::LIFE }}" @disabled(! $canEdit)></td>
                                <td><input class="input w-70" name="buildings[{{ $i }}][wear]" value="{{ $num($b['wear'] ?? null) }}" inputmode="decimal" placeholder="{{ __('by age') }}" @disabled(! $canEdit)></td>
                                <td><input class="input w-70" name="buildings[{{ $i }}][functional]" value="{{ $num($b['functional'] ?? null) }}" inputmode="decimal" placeholder="0" @disabled(! $canEdit)></td>
                                <td><input class="input w-70" name="buildings[{{ $i }}][external]" value="{{ $num($b['external'] ?? null) }}" inputmode="decimal" placeholder="0" @disabled(! $canEdit)></td>
                                <td class="c"><input type="checkbox" name="buildings[{{ $i }}][light]" value="1" @checked($b['light'] ?? false) @disabled(! $canEdit) aria-label="{{ __('Light structural element: economic life 40 years') }}"></td>
                                <td>@if ($canEdit)<button type="button" class="btn btn-sm btn-icon btn-ghost" data-row-remove title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}"><x-icon name="x" :size="14" /></button>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @foreach ($errors->get('buildings.*') as $messages)<p class="field-error">{{ $messages[0] }}</p>@endforeach
            @php $actWear = \App\Reports\InspectionAct::build(\App\Reports\RealEstateReport::values($item))['wear']; @endphp
            @if ($actWear !== null)
                <p class="field-hint" style="margin-top: 8px"><x-icon name="clipboard-check" :size="14" /> {{ __('Physical wear by the inspection act: :wear %. It can be written into "Physical wear, %" instead of the wear by age.', ['wear' => Format::number($actWear, 1)]) }}</p>
            @endif

            <div class="form-grid" style="margin-top: 12px">
                <x-field name="profit" :label="__('Developer profit, %')" :value="$costInput['profit'] ?? 0" span="3" suffix="%" :disabled="! $canEdit" />
                <x-field name="land" :label="__('Land value (right of use)')" :value="$costInput['land'] ?? 0" money :suffix="__('sum')" span="5" :disabled="! $canEdit" />
                @if ($canEdit)
                    <div class="field span-4 approach-buttons">
                        <button type="button" class="btn btn-sm" data-row-add><x-icon name="plus" :size="14" /> {{ __('Add a building') }}</button>
                        <button class="btn btn-primary btn-sm" type="submit"><x-icon name="refresh-cw" :size="14" /> {{ __('Save and recalculate') }}</button>
                    </div>
                @endif
            </div>
        </form>

        @if ($cost['rows'])
            <div class="table-wrap" style="margin-top: 14px">
                <table class="table table-compact">
                    <thead><tr><th>{{ __('Building') }}</th><th class="num">{{ __('Replacement cost') }}</th><th class="num">{{ __('Physical wear, %') }}</th><th class="num">{{ __('Total depreciation, %') }}</th><th class="num">{{ __('Value') }}</th></tr></thead>
                    <tbody>
                        @foreach ($cost['rows'] as $row)
                            <tr><td>{{ $row['name'] ?: '—' }}</td><td class="num">{{ Format::money($row['replacement']) }}</td><td class="num">{{ $num($row['physical']) }}</td><td class="num">{{ $num($row['depreciation']) }}</td><td class="num"><strong>{{ Format::money($row['value']) }}</strong></td></tr>
                        @endforeach
                        @if ($cost['land'] > 0)<tr><td>{{ __('Land value (right of use)') }}</td><td colspan="3"></td><td class="num">{{ Format::money($cost['land']) }}</td></tr>@endif
                        <tr class="total"><td colspan="4">{{ __('Value by the cost approach') }}</td><td class="num"><strong>{{ Format::money($cost['value']) }}</strong></td></tr>
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
@endif

@if (in_array(ValuationApproach::Income, $applied, true) && ($only ?? 'income') === 'income')
    @php
        $incomeInput = $report['income'] ?? ['area' => $item->useful_area ?? $item->total_area, 'rent' => null, 'vacancy' => 10, 'opex' => 15, 'cap' => 14];
        $income = IncomeApproach::calculate($incomeInput);
    @endphp
    <x-card :title="__('Income approach')" icon="trending-up" id="income-approach">
        <x-slot:actions>@if ($income['value'])<span class="badge badge-brand">{{ Format::money($income['value']) }}</span>@endif</x-slot:actions>
        <form method="post" action="{{ route('admin.real-estates.approaches.income', $item->id) }}">
            @csrf @method('put')
            <div class="form-grid">
                <x-field name="area" :label="__('Rentable area')" :value="$incomeInput['area']" money :suffix="__('m²')" span="3" :disabled="! $canEdit" />
                <x-field name="rent" :label="__('Monthly rent per m²')" :value="$incomeInput['rent']" money :suffix="__('sum')" span="3" :disabled="! $canEdit" />
                <x-field name="vacancy" :label="__('Vacancy and losses, %')" :value="$incomeInput['vacancy']" span="2" suffix="%" :disabled="! $canEdit" />
                <x-field name="opex" :label="__('Operating expenses, %')" :value="$incomeInput['opex']" span="2" suffix="%" :disabled="! $canEdit" />
                <x-field name="cap" :label="__('Capitalisation rate, %')" :value="$incomeInput['cap']" span="2" suffix="%" :disabled="! $canEdit" />
            </div>
            @if ($canEdit)
                <div class="approach-buttons" style="margin-top: 10px"><button class="btn btn-primary btn-sm" type="submit"><x-icon name="refresh-cw" :size="14" /> {{ __('Save and recalculate') }}</button></div>
            @endif
        </form>
        @if ($income['pgi'] > 0)
            <div class="grid grid-4 approach-results">
                <div class="result-box"><span>{{ __('Potential gross income per year') }}</span><strong>{{ Format::money($income['pgi']) }}</strong></div>
                <div class="result-box"><span>{{ __('Effective gross income') }}</span><strong>{{ Format::money($income['egi']) }}</strong></div>
                <div class="result-box"><span>{{ __('Net operating income') }}</span><strong>{{ Format::money($income['noi']) }}</strong></div>
                <div class="result-box"><span>{{ __('Value by the income approach') }}</span><strong>{{ $income['value'] ? Format::money($income['value']) : '—' }}</strong></div>
            </div>
        @endif
    </x-card>
@endif
