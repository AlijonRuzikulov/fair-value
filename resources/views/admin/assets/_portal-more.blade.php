{{-- What the portal asks for in its other windows, once the object is saved: the data of the property and its value. --}}
@if ($item->exists)
    <details class="portal-fold" id="object-data" data-remember>
        <summary>{{ __('Data of the property') }}</summary>
        <fieldset class="portal-fields" @disabled(! $canEdit)>
            <div class="form-grid">
                <x-select name="category" :label="__('Category')" :options="$categories" :value="$item->category" placeholder="" span="3" />
                <x-field name="inventory_number" :label="__('Inventory number')" :value="$item->inventory_number" span="3" />
                <x-field name="manufacturer" :label="__('Manufacturer')" :value="$item->manufacturer" span="3" />
                <x-field name="model" :label="__('Model')" :value="$item->model" span="3" />
                <x-field name="serial_number" :label="__('Serial number')" :value="$item->serial_number" span="3" />
                <x-field name="year_made" type="number" :label="__('Year of manufacture')" :value="$item->year_made" span="3" />
                <x-field name="quantity" :label="__('Quantity')" :value="$item->quantity" money span="3" required />
                <x-select name="unit" :label="__('Unit')" :options="$units" :value="$item->unit" span="3" />
                <x-field name="condition" :label="__('Technical condition')" :value="$item->condition" span="12" />
                <x-select name="region_id" :label="__('Region')" :options="$regions->pluck('name', 'id')" :value="$item->region_id" placeholder="" span="4" data-region-select />
                <x-select name="district_id" :label="__('District')" :options="$districts" :value="$item->district_id" placeholder="" span="4" data-district-select />
                <x-field name="address" :label="__('Address')" :value="$item->address" span="4" />
            </div>
        </fieldset>
    </details>
    @include('admin.objects._portal-valuation')
@endif
