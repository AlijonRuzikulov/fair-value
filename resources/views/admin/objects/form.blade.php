@php
    $params = $module->routeParams($item);
    $title = $item->exists ? $item->title : __('New').': '.mb_strtolower($module->singular());
    $back = $item->exists ? route($module->routeName('show'), $item->id) : route($module->routeName('index'), $params);
@endphp
<x-layouts.admin :title="$title" :subtitle="$item->exists ? __('Editing') : $module->title()" :nav="$nav" :icon="$module->icon()" :back="$back">
    <form method="post" class="form-layout" data-object-form
          action="{{ $item->exists ? route($module->routeName('update'), $item->id) : route($module->routeName('store')) }}">
        @csrf
        @if ($item->exists) @method('put') @endif

        @if ($errors->any())
            <div class="toast toast-error" role="alert"><x-icon name="triangle-alert" :size="18" /> {{ __('Please correct the highlighted fields.') }}</div>
        @endif

        <x-card :title="__('Main information')" icon="file-text">
            <div class="form-grid">
                @include('admin.'.$module->slugName().'._fields')
            </div>
        </x-card>

        <x-card :title="__('Location')" icon="map-pin">
            <div class="form-grid">
                @include('admin.objects._location')
            </div>
        </x-card>

        <x-card :title="__('Persons')" icon="users">
            @include('admin.objects._persons')
        </x-card>

        @includeIf('admin.'.$module->slugName().'._form-extra')

        <x-card :title="$module->usesApproaches() ? __('Valuation') : __('Assessment')" icon="scale">
            <div class="form-grid">
                <x-select name="purpose" :label="__('Purpose of valuation')" :options="$purposes" :value="$item->purpose" placeholder="—" span="6" />
                <x-field name="valuation_date" type="date" :label="__('Valuation date')" :value="$item->valuation_date" span="3" />
                <x-field name="inspection_date" type="date" :label="__('Inspection date')" :value="$item->inspection_date" span="3" />

                @if ($module->usesApproaches())
                    @php $applied = array_map('strval', (array) old('approaches', $item->approaches ?? [])); @endphp
                    <p class="form-section-title">{{ __('Approaches applied') }}</p>
                    @foreach ($approachOptions as $value => $label)
                        <div class="field span-4" data-approach>
                            <label class="check">
                                <input type="checkbox" name="approaches[]" value="{{ $value }}" @checked(in_array($value, $applied, true)) data-approach-toggle>
                                <span class="check-box"><x-icon name="check" :size="14" /></span>
                                <span>{{ $label }}</span>
                            </label>
                            <x-field :name="'value_'.$value" :value="$item->{'value_'.$value}" money :suffix="__('sum')" span="12" :placeholder="__('Value by this approach')" data-approach-value />
                        </div>
                    @endforeach
                    <x-field name="market_value" :label="__('Market value (final)')" :value="$item->market_value" money :suffix="__('sum')" span="6" :hint="__('The result of reconciling the approaches.')" />
                @endif
            </div>
        </x-card>

        <x-card :title="__('Contract and report')" icon="file-check">
            <div class="form-grid">
                <x-field name="contract_number" :label="__('Contract number')" :value="$item->contract_number" span="3" />
                <x-field name="contract_date" type="date" :label="__('Contract date')" :value="$item->contract_date" span="3" />
                <x-field name="service_fee" :label="__('Service fee')" :value="$item->service_fee" money :suffix="__('sum')" span="3" />
                <x-field name="report_number" :label="__('Report number')" :value="$item->report_number" span="3" :hint="__('Assigned automatically when completed.')" />
                <x-select name="status" :label="__('Status')" :options="$statuses" :value="$item->status" span="4" required />
                <x-select name="appraiser_id" :label="__('Appraiser')" :options="$appraisers" :value="$item->appraiser_id" placeholder="—" span="4" />
                <x-textarea name="notes" :label="__('Notes')" :value="$item->notes" span="12" />
            </div>
        </x-card>

        <div class="card form-actions">
            <a class="btn btn-ghost" href="{{ $back }}">{{ __('Cancel') }}</a>
            <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> {{ __('Save') }}</button>
        </div>
    </form>
</x-layouts.admin>
