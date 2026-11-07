@php $value = fn (string $key) => $organization->settings[$key] ?? null; @endphp
<x-layouts.admin :title="$organization->exists ? $organization->name : __('New organisation')" :subtitle="__('Organisations')" nav="organizations" icon="building-2" :back="route('admin.organizations.index')">
    <form method="post" class="form-layout" action="{{ $organization->exists ? route('admin.organizations.update', $organization) : route('admin.organizations.store') }}">
        @csrf
        @if ($organization->exists) @method('put') @endif
        <x-card :title="__('Organisation')" icon="building-2">
            <div class="form-grid">
                <x-field name="name" :label="__('Name')" :value="$organization->name" span="6" required :placeholder="__('Bank, valuation company or department')" />
                <x-field name="legal_name" :label="__('Legal name')" :value="$value('legal_name')" span="6" />
                <x-field name="legal_name_ru" :label="__('Legal name in Russian')" :value="$value('legal_name_ru')" span="6" />
                <x-field name="director" :label="__('Director')" :value="$value('director')" span="3" />
                <x-field name="chief_accountant" :label="__('Chief accountant')" :value="$value('chief_accountant')" span="3" />
                <x-field name="inn" :label="__('TIN')" :value="$value('inn')" span="2" />
                <x-field name="oked" :label="__('OKED')" :value="$value('oked')" span="2" />
                <x-field name="license" :label="__('License / insurance policy')" :value="$value('license')" span="8" />
                <x-field name="insurance_policy" :label="__('Insurance policy of the appraisers')" :value="$value('insurance_policy')" span="12" />
                <x-textarea name="membership" :label="__('Membership certificate of the appraisers\' association')" :value="$value('membership')" rows="2" />
            </div>
        </x-card>
        <x-card :title="__('Contacts and bank details')" icon="landmark">
            <div class="form-grid">
                <x-field name="phone" :label="__('Phone')" :value="$value('phone')" span="4" />
                <x-field name="phone2" :label="__('Additional phone')" :value="$value('phone2')" span="4" />
                <x-field name="email" type="email" :label="__('E-mail')" :value="$value('email')" span="4" />
                <x-field name="address" :label="__('Address')" :value="$value('address')" span="12" />
                <x-field name="bank_name" :label="__('Bank')" :value="$value('bank_name')" span="5" />
                <x-field name="bank_account" :label="__('Bank account')" :value="$value('bank_account')" span="5" />
                <x-field name="bank_mfo" :label="__('MFO')" :value="$value('bank_mfo')" span="2" />
            </div>
        </x-card>
        <x-card :title="__('Contracts and reports')" icon="file-text">
            <div class="form-grid">
                <x-field name="report_city" :label="__('City printed on reports')" :value="$value('report_city')" span="4" :placeholder="config('fairvalue.company.report_city')" />
                <x-field name="vat_rate" :label="__('VAT rate, %')" :value="$value('vat_rate')" span="2" placeholder="0" />
                <x-field name="contract_number_format" :label="__('Format of contract numbers')" :value="$value('contract_number_format')" span="3" :placeholder="config('fairvalue.company.contract_number_format')" />
                <x-field name="report_number_format" :label="__('Format of report numbers')" :value="$value('report_number_format')" span="3" :placeholder="config('fairvalue.company.report_number_format')" />
                <x-select name="require_approval" :label="__('Completion of reports')" :options="['0' => __('The appraiser completes the report'), '1' => __('The director approves the report')]" :value="$value('require_approval') ?? '0'" span="6" />
            </div>
        </x-card>
        <div class="card form-actions">
            <a class="btn btn-ghost" href="{{ route('admin.organizations.index') }}">{{ __('Cancel') }}</a>
            <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> {{ __('Save') }}</button>
        </div>
    </form>
</x-layouts.admin>
