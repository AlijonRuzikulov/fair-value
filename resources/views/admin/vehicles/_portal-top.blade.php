{{-- "Avtotransport qo'shish" of the valuation portal, in its two columns. --}}
<div class="portal-cols">
    <div class="portal-col form-grid">
        <x-select name="property_type" :label="__('Type of the property')" :options="$propertyTypes" :value="$item->property_type" span="12" required />
        @include('admin.objects._portal-owner')
    </div>
    <div class="portal-col form-grid">
        <x-select name="purpose" :label="__('Purpose of valuation')" :options="$portalPurposes" :value="$item->purpose" placeholder="" span="12" required />
        <x-select name="ownership_form" :label="__('Form of ownership')" :options="$ownershipForms" :value="$item->ownership_form" placeholder="" span="12" required />
        <x-select name="appraiser_id" :label="__('Appraiser')" :options="$appraisers" :value="$item->appraiser_id" placeholder="" span="12" required />
        @include('admin.objects._portal-contract')
        <x-textarea name="identification" :label="__('Opinion of the appraiser on the identification')" :value="$item->identification" rows="2" />
        <x-textarea name="restrictions" :label="__('Opinion of the appraiser on the restricting conditions')" :value="$item->restrictions" rows="2" />
    </div>
</div>
