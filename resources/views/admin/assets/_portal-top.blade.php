{{-- "Aralash mol-mulk qo'shish" of the valuation portal, in its two columns. --}}
<div class="portal-cols">
    <div class="portal-col form-grid">
        <x-select name="property_type" :label="__('Type of the property')" :options="$propertyTypes" :value="$item->property_type" span="12" required />
        @include('admin.objects._portal-owner')
        <x-select name="purpose" :label="__('Purpose of valuation')" :options="$portalPurposes" :value="$item->purpose" placeholder="" span="12" required />
        <x-field name="name" :label="__('Object')" :value="$item->name" span="12" required />
    </div>
    <div class="portal-col form-grid">
        <x-select name="ownership_form" :label="__('Form of ownership')" :options="$ownershipForms" :value="$item->ownership_form" placeholder="" span="12" required />
        <x-select name="appraiser_id" :label="__('Appraiser')" :options="$appraisers" :value="$item->appraiser_id" placeholder="" span="12" required />
        @include('admin.objects._portal-contract')
    </div>
</div>
