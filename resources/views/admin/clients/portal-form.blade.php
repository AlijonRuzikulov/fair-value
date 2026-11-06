{{-- The form of a client in the classic look, in the two columns of the valuation portal. --}}
@php
    use App\Enums\ClientType;
    use App\Models\Client;
    $legal = $client->type === ClientType::Legal;
    $back = $client->exists ? route('admin.clients.show', $client) : route('admin.clients.index', ['type' => $client->type->value]);
    $list = fn (array $values, ?string $current) => collect($values)->when(filled($current) && ! in_array($current, $values, true), fn ($c) => $c->push($current))
        ->mapWithKeys(fn ($v) => [$v => app()->getLocale() === 'oz' ? \App\Support\Transliterator::toCyrillic($v) : \App\Reports\ReportScript::latin($v)])->all();
    [$headLast, $headFirst, $headMiddle] = $client->directorParts();
    $passport = trim($client->passport_series.' '.$client->passport_number);
@endphp
<x-layouts.admin :title="$legal ? __('Data of a legal entity') : __('Data of an individual')" :nav="'clients.'.$client->type->value" :icon="$legal ? 'building-2' : 'user'">
    <section class="card portal-card">
        <header class="portal-card-head">
            <h2>{{ $legal ? __('Enter the data of the legal entity') : __('Enter the data of the individual') }}</h2>
        </header>
        <form method="post" class="portal-card-body" action="{{ $client->exists ? route('admin.clients.update', $client) : route('admin.clients.store') }}">
            @csrf
            @if ($client->exists) @method('put') @else <input type="hidden" name="type" value="{{ $client->type->value }}"> @endif
            <input type="hidden" name="_portal" value="1">

            @if ($errors->any())
                <div class="toast toast-error" role="alert"><x-icon name="triangle-alert" :size="18" /> {{ __('Please correct the highlighted fields.') }}</div>
            @endif

            <div class="portal-cols">
                @if ($legal)
                    <div class="portal-col form-grid">
                        <x-select name="organization" :label="__('Organisation')" :options="$list(Client::ORGANIZATIONS, $client->organization)" :value="$client->organization" placeholder="" span="12" required />
                        <x-select name="management" :label="__('Form of management')" :options="$list(Client::MANAGEMENT, $client->management)" :value="$client->management" placeholder="" span="12" required />
                        <x-field name="company_name" :label="__('Name of the organisation')" :value="$client->company_name" span="12" required />
                        <x-field name="director_last_name" :label="__('Last name of the head')" :value="$headLast" span="12" required />
                        <x-field name="director_first_name" :label="__('First name of the head')" :value="$headFirst" span="12" required />
                        <x-field name="director_middle_name" :label="__('Patronymic of the head')" :value="$headMiddle" span="12" required />
                        <x-field name="director_position" :label="__('Position')" :value="$client->director_position" span="12" required />
                        <x-select name="region_id" :label="__('Region of the client')" :options="$regions->pluck('name', 'id')" :value="$client->region_id" placeholder="" span="12" required data-region-select />
                        <x-select name="district_id" :label="__('District of the client')" :options="$districts" :value="$client->district_id" placeholder="" span="12" required data-district-select />
                        <x-field name="address" :label="__('Address of the organisation')" :value="$client->address" span="12" required />
                    </div>
                    <div class="portal-col form-grid">
                        <x-field name="phone" type="tel" :label="__('Phone number')" :value="$client->phone" span="12" required placeholder="+998 (XX) XXX XXXX" data-empty-alert="{{ __('Enter the phone number!') }}" />
                        <x-field name="email" type="email" :label="__('E-mail')" :value="$client->email" span="12" required />
                        <x-field name="bank_account" :label="__('Account number')" :value="$client->bank_account" span="12" required inputmode="numeric" maxlength="24" data-empty-alert="{{ __('Enter the account number!') }}" />
                        <x-field name="inn" :label="__('TIN (INN)')" :value="$client->inn" span="12" required inputmode="numeric" maxlength="9" data-empty-alert="{{ __('Enter the TIN!') }}" />
                        <x-field name="bank_mfo" :label="__('MFO')" :value="$client->bank_mfo" span="12" required inputmode="numeric" maxlength="5" data-empty-alert="{{ __('Enter the MFO!') }}" />
                        <x-field name="bank_name" :label="__('Name of the bank')" :value="$client->bank_name" span="12" required />
                        <div class="field span-12">
                            <input type="hidden" name="single_tax" value="0">
                            <label class="tick"><input type="checkbox" name="single_tax" value="1" @checked(old('single_tax', $client->single_tax))> <span>{{ __('Single tax payer') }}</span></label>
                        </div>
                    </div>
                @else
                    <div class="portal-col form-grid">
                        <x-field name="last_name" :label="__('Last name')" :value="$client->last_name" span="12" required />
                        <x-field name="first_name" :label="__('First name')" :value="$client->first_name" span="12" required />
                        <x-field name="middle_name" :label="__('Patronymic')" :value="$client->middle_name" span="12" required />
                        <x-select name="region_id" :label="__('Region of the client')" :options="$regions->pluck('name', 'id')" :value="$client->region_id" placeholder="" span="12" required data-region-select />
                        <x-select name="district_id" :label="__('District of the client')" :options="$districts" :value="$client->district_id" placeholder="" span="12" required data-district-select />
                        <x-field name="address" :label="__('Address of the client')" :value="$client->address" span="12" required />
                    </div>
                    <div class="portal-col form-grid">
                        <x-field name="phone" type="tel" :label="__('Phone number')" :value="$client->phone" span="12" required placeholder="+998 (XX) XXX XXXX" data-empty-alert="{{ __('Enter the phone number!') }}" />
                        <x-field name="passport" :label="__('Passport series and number')" :value="old('passport', $passport ?: (old('passport_series') ? old('passport_series').' '.old('passport_number') : null))" span="12" required placeholder="AA 1234567" data-empty-alert="{{ __('Enter the passport series and number!') }}" />
                        <x-field name="pinfl" :label="__('Personal identification number of the individual (PINFL, 14 digits)')" :value="$client->pinfl" span="12" required inputmode="numeric" maxlength="14" />
                        <x-field name="passport_issued_at" type="date" :label="__('Date the passport was issued')" :value="$client->passport_issued_at" span="12" required />
                        <x-field name="passport_issued_by" :label="__('Issued by')" :value="$client->passport_issued_by" span="12" required />
                    </div>
                @endif
            </div>

            <div class="portal-actions portal-center">
                <a class="btn btn-fill-danger" href="{{ $back }}">{{ __('Cancel') }}</a>
                <button class="btn btn-success" type="submit">{{ __('Save') }}</button>
            </div>
        </form>
    </section>
</x-layouts.admin>
