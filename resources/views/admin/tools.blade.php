<x-layouts.admin :title="__('Valuation plus')" :subtitle="__('Calculators for the three approaches')" nav="other.tools" icon="calculator">
    <section class="card">
        <div class="card-body">
            <div class="tool-tabs" role="tablist">
                <button type="button" class="btn btn-primary" role="tab" data-tool-tab="comparative" aria-selected="true"><x-icon name="scale" :size="16" /> {{ __('Comparative approach') }}</button>
                <button type="button" class="btn" role="tab" data-tool-tab="cost" aria-selected="false"><x-icon name="hammer" :size="16" /> {{ __('Cost approach') }}</button>
                <button type="button" class="btn" role="tab" data-tool-tab="income" aria-selected="false"><x-icon name="trending-up" :size="16" /> {{ __('Income approach') }}</button>
                <button type="button" class="btn" role="tab" data-tool-tab="final" aria-selected="false"><x-icon name="badge-check" :size="16" /> {{ __('Reconciliation') }}</button>
                <button type="button" class="btn" role="tab" data-tool-tab="currency" aria-selected="false"><x-icon name="banknote" :size="16" /> {{ __('Converter') }}</button>
            </div>
        </div>
    </section>

    {{-- Comparative approach: sale offers of similar objects adjusted to the subject --}}
    <section class="tool-panel stack" data-tool-panel="comparative">
        <x-card :title="__('Comparative approach')" icon="scale">
            <p class="field-hint" style="margin-bottom: 14px">{{ __('Enter offers of similar objects. Adjustments are percentages: positive if the analogue is worse than the object being valued, negative if it is better.') }}</p>
            <div class="form-grid" style="margin-bottom: 16px">
                <x-field name="cmp_area" :label="__('Area of the object')" money :suffix="__('m²')" span="3" value="100" data-calc-input />
            </div>
            <div class="table-wrap">
                <table class="table analog-table">
                    <thead>
                        <tr>
                            <th>{{ __('Analogue') }}</th>
                            <th>{{ __('Offer price, sum') }}</th>
                            <th>{{ __('Area, m²') }}</th>
                            <th>{{ __('Bargaining, %') }}</th>
                            <th>{{ __('Location, %') }}</th>
                            <th>{{ __('Condition, %') }}</th>
                            <th>{{ __('Other, %') }}</th>
                            <th>{{ __('Weight') }}</th>
                            <th class="t-right">{{ __('Adjusted price per m²') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @for ($i = 1; $i <= 5; $i++)
                            <tr data-analog>
                                <td class="strong">№{{ $i }}</td>
                                <td><input class="input" data-money data-a="price" inputmode="decimal" aria-label="{{ __('Offer price, sum') }}"></td>
                                <td><input class="input" data-money data-a="area" inputmode="decimal" aria-label="{{ __('Area, m²') }}" style="width: 90px"></td>
                                <td><input class="input" data-a="bargain" value="-5" inputmode="decimal" aria-label="{{ __('Bargaining, %') }}" style="width: 72px"></td>
                                <td><input class="input" data-a="location" value="0" inputmode="decimal" aria-label="{{ __('Location, %') }}" style="width: 72px"></td>
                                <td><input class="input" data-a="condition" value="0" inputmode="decimal" aria-label="{{ __('Condition, %') }}" style="width: 72px"></td>
                                <td><input class="input" data-a="other" value="0" inputmode="decimal" aria-label="{{ __('Other, %') }}" style="width: 72px"></td>
                                <td><input class="input" data-a="weight" value="1" inputmode="decimal" aria-label="{{ __('Weight') }}" style="width: 64px"></td>
                                <td class="t-right cell-num" data-a-out>—</td>
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
        </x-card>
        <div class="grid grid-3">
            <div class="result-box"><span>{{ __('Weighted price per m²') }}</span><strong data-out="cmp_m2">—</strong></div>
            <div class="result-box"><span>{{ __('Value by the comparative approach') }}</span><strong data-out="cmp_value">—</strong></div>
            <div class="result-box"><span>{{ __('Spread of analogues') }}</span><strong data-out="cmp_spread">—</strong></div>
        </div>
    </section>

    {{-- Cost approach: replacement cost less depreciation plus land --}}
    <section class="tool-panel stack" data-tool-panel="cost" hidden>
        <x-card :title="__('Cost approach')" icon="hammer">
            <div class="form-grid">
                <x-field name="cost_area" :label="__('Building area')" money :suffix="__('m²')" span="3" value="100" />
                <x-field name="cost_rate" :label="__('Replacement cost per m²')" money :suffix="__('sum')" span="3" value="6 500 000" />
                <x-field name="cost_extra" :label="__('Developer profit, %')" span="2" value="10" suffix="%" />
                <x-field name="cost_age" :label="__('Actual age, years')" span="2" value="15" />
                <x-field name="cost_life" :label="__('Economic life, years')" span="2" value="80" />
                <x-field name="cost_func" :label="__('Functional obsolescence, %')" span="3" value="0" suffix="%" />
                <x-field name="cost_ext" :label="__('External obsolescence, %')" span="3" value="0" suffix="%" />
                <x-field name="cost_land" :label="__('Land value (right of use)')" money :suffix="__('sum')" span="3" value="0" />
            </div>
        </x-card>
        <div class="grid grid-3">
            <div class="result-box"><span>{{ __('Replacement cost') }}</span><strong data-out="cost_new">—</strong></div>
            <div class="result-box"><span>{{ __('Physical depreciation') }}</span><strong data-out="cost_phys">—</strong></div>
            <div class="result-box"><span>{{ __('Value by the cost approach') }}</span><strong data-out="cost_value">—</strong></div>
        </div>
    </section>

    {{-- Income approach: direct capitalisation of net operating income --}}
    <section class="tool-panel stack" data-tool-panel="income" hidden>
        <x-card :title="__('Income approach')" icon="trending-up">
            <div class="form-grid">
                <x-field name="inc_area" :label="__('Rentable area')" money :suffix="__('m²')" span="3" value="100" />
                <x-field name="inc_rent" :label="__('Monthly rent per m²')" money :suffix="__('sum')" span="3" value="120 000" />
                <x-field name="inc_vacancy" :label="__('Vacancy and losses, %')" span="2" value="10" suffix="%" />
                <x-field name="inc_opex" :label="__('Operating expenses, %')" span="2" value="15" suffix="%" />
                <x-field name="inc_cap" :label="__('Capitalisation rate, %')" span="2" value="14" suffix="%" />
            </div>
        </x-card>
        <div class="grid grid-3">
            <div class="result-box"><span>{{ __('Potential gross income per year') }}</span><strong data-out="inc_pgi">—</strong></div>
            <div class="result-box"><span>{{ __('Net operating income') }}</span><strong data-out="inc_noi">—</strong></div>
            <div class="result-box"><span>{{ __('Value by the income approach') }}</span><strong data-out="inc_value">—</strong></div>
        </div>
    </section>

    {{-- Reconciliation of the results --}}
    <section class="tool-panel stack" data-tool-panel="final" hidden>
        <x-card :title="__('Reconciliation of results')" icon="badge-check">
            <p class="field-hint" style="margin-bottom: 14px">{{ __('Results of the other tabs are copied here automatically; you can also type them in. Weights are percentages and should add up to 100.') }}</p>
            <div class="form-grid">
                <x-field name="fin_cmp" :label="__('Comparative approach')" money :suffix="__('sum')" span="4" />
                <x-field name="fin_cmp_w" :label="__('Weight, %')" span="2" value="50" suffix="%" />
                <div class="span-6"></div>
                <x-field name="fin_cost" :label="__('Cost approach')" money :suffix="__('sum')" span="4" />
                <x-field name="fin_cost_w" :label="__('Weight, %')" span="2" value="30" suffix="%" />
                <div class="span-6"></div>
                <x-field name="fin_inc" :label="__('Income approach')" money :suffix="__('sum')" span="4" />
                <x-field name="fin_inc_w" :label="__('Weight, %')" span="2" value="20" suffix="%" />
            </div>
        </x-card>
        <div class="grid grid-3">
            <div class="result-box"><span>{{ __('Sum of weights') }}</span><strong data-out="fin_w">—</strong></div>
            <div class="result-box"><span>{{ __('Market value') }}</span><strong data-out="fin_value">—</strong></div>
            <div class="result-box"><span>{{ __('Rounded') }}</span><strong data-out="fin_round">—</strong></div>
        </div>
    </section>

    {{-- Currency converter at the Central Bank rate --}}
    <section class="tool-panel stack" data-tool-panel="currency" hidden>
        <x-card :title="__('Converter')" icon="banknote">
            <div class="form-grid">
                <x-field name="cur_sum" :label="__('Amount in sum')" money :suffix="__('sum')" span="4" value="1 000 000 000" />
                <x-field name="cur_rate" :label="__('USD rate')" money span="4" :value="$usd" :hint="__('Central Bank rate; can be changed.')" />
            </div>
        </x-card>
        <div class="grid grid-3">
            <div class="result-box"><span>{{ __('Amount in US dollars') }}</span><strong data-out="cur_usd">—</strong></div>
        </div>
    </section>

    @push('scripts')
        <script src="{{ asset('js/tools.js') }}?v={{ filemtime(public_path('js/tools.js')) }}" defer></script>
    @endpush
</x-layouts.admin>
