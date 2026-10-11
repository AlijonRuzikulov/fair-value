@php
    use App\Enums\ClientType;
    $legal = $client->type === ClientType::Legal;
    $back = $client->exists ? route('admin.clients.show', $client) : route('admin.clients.index', ['type' => $client->type->value]);
@endphp
<x-layouts.admin :title="$client->exists ? $client->title : __('New client').': '.mb_strtolower($client->type->label())" :subtitle="__('Clients')" :nav="'clients.'.$client->type->value" :icon="$legal ? 'building-2' : 'user'" :back="$back">
    <form method="post" class="form-layout" action="{{ $client->exists ? route('admin.clients.update', $client) : route('admin.clients.store') }}">
        @csrf
        @if ($client->exists) @method('put') @else <input type="hidden" name="type" value="{{ $client->type->value }}"> @endif

        @if ($errors->any())
            <div class="toast toast-error" role="alert"><x-icon name="triangle-alert" :size="18" /> {{ __('Please correct the highlighted fields.') }}</div>
        @endif

        @if ($legal)
            <x-card :title="__('Company details')" icon="building-2">
                <div class="form-grid">
                    <x-field name="company_name" :label="__('Company name')" :value="$client->company_name" span="8" required placeholder='"Example" MChJ' />
                    <x-field name="inn" :label="__('TIN')" :value="$client->inn" span="4" inputmode="numeric" maxlength="9" placeholder="123456789" />
                    <x-field name="director" :label="__('Director')" :value="$client->director" span="6" />
                    <x-field name="oked" :label="__('OKED')" :value="$client->oked" span="3" />
                    <x-field name="bank_mfo" :label="__('MFO')" :value="$client->bank_mfo" span="3" inputmode="numeric" maxlength="5" />
                    <x-field name="bank_name" :label="__('Bank')" :value="$client->bank_name" span="6" />
                    <x-field name="bank_account" :label="__('Bank account')" :value="$client->bank_account" span="6" inputmode="numeric" maxlength="24" placeholder="20208000…" />
                </div>
            </x-card>
        @else
            <x-card :title="__('Personal details')" icon="user">
                <div class="form-grid">
                    <x-field name="last_name" :label="__('Last name')" :value="$client->last_name" span="4" required />
                    <x-field name="first_name" :label="__('First name')" :value="$client->first_name" span="4" required />
                    <x-field name="middle_name" :label="__('Middle name')" :value="$client->middle_name" span="4" />
                    <x-field name="birth_date" type="date" :label="__('Date of birth')" :value="$client->birth_date" span="3" />
                    <x-field name="pinfl" :label="__('PINFL')" :value="$client->pinfl" span="3" inputmode="numeric" maxlength="14" :hint="__('14 digits')" />
                    <x-field name="passport_series" :label="__('Passport series')" :value="$client->passport_series" span="2" maxlength="4" placeholder="AA" />
                    <x-field name="passport_number" :label="__('Passport number')" :value="$client->passport_number" span="4" placeholder="1234567" />
                    <x-field name="passport_issued_by" :label="__('Issued by')" :value="$client->passport_issued_by" span="8" />
                    <x-field name="passport_issued_at" type="date" :label="__('Date of issue')" :value="$client->passport_issued_at" span="4" />
                </div>
            </x-card>
        @endif

        <x-card :title="__('Contacts and address')" icon="phone">
            <div class="form-grid">
                <x-field name="phone" type="tel" :label="__('Phone')" :value="$client->phone" span="4" placeholder="+998 90 123-45-67" />
                <x-field name="phone2" type="tel" :label="__('Additional phone')" :value="$client->phone2" span="4" />
                <x-field name="email" type="email" :label="__('E-mail')" :value="$client->email" span="4" />
                <x-select name="region_id" :label="__('Region')" :options="$regions->pluck('name', 'id')" :value="$client->region_id" placeholder="—" span="4" data-region-select />
                <x-select name="district_id" :label="__('District / city')" :options="$districts" :value="$client->district_id" placeholder="—" span="4" data-district-select />
                <x-field name="address" :label="__('Address')" :value="$client->address" span="4" />
                <x-textarea name="notes" :label="__('Notes')" :value="$client->notes" />
            </div>
        </x-card>

        <div class="card form-actions">
            <a class="btn btn-ghost" href="{{ $back }}">{{ __('Cancel') }}</a>
            <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> {{ __('Save') }}</button>
        </div>
    </form>
</x-layouts.admin>
