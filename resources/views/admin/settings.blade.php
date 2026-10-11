@php use App\Support\Format; @endphp
<x-layouts.admin :title="__('Settings')" nav="settings" icon="settings">
    <form method="post" class="form-layout" action="{{ route('admin.settings.update') }}">
        @csrf @method('put')
        <x-card :title="__('Company')" icon="building-2">
            <div class="form-grid">
                <x-field name="name" :label="__('Brand name')" :value="$values['name']" span="4" required />
                <x-field name="legal_name" :label="__('Legal name')" :value="$values['legal_name']" span="4" />
                <x-field name="director" :label="__('Director')" :value="$values['director']" span="4" />
                <x-field name="license" :label="__('License / insurance policy')" :value="$values['license']" span="6" :hint="__('Shown on the website and printed certificates.')" />
                <x-field name="inn" :label="__('TIN')" :value="$values['inn']" span="2" />
                <x-field name="bank_name" :label="__('Bank')" :value="$values['bank_name']" span="4" />
                <x-field name="bank_account" :label="__('Bank account')" :value="$values['bank_account']" span="4" />
                <x-field name="bank_mfo" :label="__('MFO')" :value="$values['bank_mfo']" span="2" />
            </div>
        </x-card>

        <x-card :title="__('Contacts on the website')" icon="phone">
            <div class="form-grid">
                <x-field name="phone" :label="__('Phone')" :value="$values['phone']" span="4" required />
                <x-field name="phone2" :label="__('Additional phone')" :value="$values['phone2']" span="4" />
                <x-field name="email" type="email" :label="__('E-mail')" :value="$values['email']" span="4" />
                <x-field name="address" :label="__('Address')" :value="$values['address']" span="8" />
                <x-field name="working_hours" :label="__('Working hours')" :value="$values['working_hours']" span="4" />
                <x-field name="telegram" :label="__('Telegram')" :value="$values['telegram']" span="4" placeholder="fairvalueuz" />
                <x-field name="instagram" :label="__('Instagram')" :value="$values['instagram']" span="4" placeholder="fairvalueuz" />
                <x-field name="map_url" type="url" :label="__('Map link')" :value="$values['map_url']" span="4" placeholder="https://yandex.uz/maps/…" />
            </div>
        </x-card>

        <x-card :title="__('Numbers on the home page')" icon="chart-column">
            <div class="form-grid">
                <x-field name="stat_years" :label="__('Years of experience')" :value="$values['stat_years']" span="3" />
                <x-field name="stat_reports" :label="__('Reports completed')" :value="$values['stat_reports']" span="3" />
                <x-field name="stat_clients" :label="__('Clients')" :value="$values['stat_clients']" span="3" />
                <x-field name="stat_banks" :label="__('Partner banks')" :value="$values['stat_banks']" span="3" />
            </div>
        </x-card>

        <x-card :title="__('Damage assessment')" icon="wrench">
            <div class="form-grid">
                <x-field name="labor_rate" :label="__('Default standard hour rate')" :value="$values['labor_rate']" money :suffix="__('sum')" span="4" :hint="__('Used for new damage assessments unless the car model has its own rate.')" />
            </div>
        </x-card>

        <div class="card form-actions">
            <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> {{ __('Save') }}</button>
        </div>
    </form>

    <x-card :title="__('Exchange rates')" icon="landmark">
        <x-slot:actions>
            <form method="post" action="{{ route('admin.settings.currency') }}">
                @csrf
                <button class="btn btn-sm" type="submit"><x-icon name="refresh-cw" :size="14" /> {{ __('Update now') }}</button>
            </form>
        </x-slot:actions>
        <p class="field-hint">{{ __('Official rates are downloaded from the Central Bank of Uzbekistan (cbu.uz) automatically every few hours.') }}</p>
        @if ($rates->isNotEmpty())
            <div class="chips" style="margin-top: 12px">
                @foreach ($rates as $rate)
                    <span class="chip">{{ $rate->code }} {{ Format::number($rate->rate, 2) }}</span>
                @endforeach
                <span class="chip chip-brand">{{ $rates->first()->rate_date->format('d.m.Y') }}</span>
            </div>
        @endif
    </x-card>
</x-layouts.admin>
