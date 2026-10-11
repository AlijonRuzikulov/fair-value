<x-select name="region_id" :label="__('Region')" :options="$regions->pluck('name', 'id')" :value="$item->region_id" placeholder="—" span="4" data-region-select />
<x-select name="district_id" :label="__('District / city')" :options="$districts" :value="$item->district_id" placeholder="—" span="4" data-district-select />
<x-field name="address" :label="__('Address')" :value="$item->address" span="4" :placeholder="__('Street, house, apartment')" />
