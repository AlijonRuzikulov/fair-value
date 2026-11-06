{{-- The car whose damage is valued, in the two columns of the valuation portal. --}}
<div class="portal-cols">
    <div class="portal-col form-grid">
        @include('admin.objects._portal-owner')
        <x-select name="purpose" :label="__('Purpose of valuation')" :options="$portalPurposes" :value="$item->purpose" placeholder="" span="12" required />
    </div>
    <div class="portal-col form-grid">
        <x-select name="appraiser_id" :label="__('Appraiser')" :options="$appraisers" :value="$item->appraiser_id" placeholder="" span="12" required />
        @include('admin.objects._portal-contract')
    </div>
</div>
