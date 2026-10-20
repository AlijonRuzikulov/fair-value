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
                <x-field name="legal_name_ru" :label="__('Legal name in Russian')" :value="$values['legal_name_ru']" span="6" :hint="__('Printed in the header of valuation reports.')" />
                <x-field name="oked" :label="__('OKED')" :value="$values['oked']" span="2" />
                <x-field name="report_city" :label="__('City printed on reports')" :value="$values['report_city']" span="4" />
                <x-field name="insurance_policy" :label="__('Insurance policy of the appraisers')" :value="$values['insurance_policy']" span="8" :hint="__('For example: policy No. … issued by … on …. Printed in service contracts.')" />
                <x-field name="vat_rate" :label="__('VAT rate, %')" :value="$values['vat_rate']" span="2" :hint="__('0 — not a VAT payer.')" />
                <x-field name="chief_accountant" :label="__('Chief accountant')" :value="$values['chief_accountant']" span="2" />
                <x-field name="contract_number_format" :label="__('Format of contract numbers')" :value="$values['contract_number_format']" span="3" :hint="__('Next: :number', ['number' => \App\Support\DocumentNumber::next('contract')])" />
                <x-field name="report_number_format" :label="__('Format of report numbers')" :value="$values['report_number_format']" span="3" :hint="__('Next: :number', ['number' => \App\Support\DocumentNumber::next('report')])" />
                <p class="field-hint span-6" style="align-self: center">{{ __('{n} — sequence number, {nnnn} — with zeros (0001), {yy} — year (26), {yyyy} — year (2026). For example 20/{n}-{yy} gives 20/551-26. Numbering continues from the largest number of the same format.') }}</p>
                <x-select name="require_approval" :label="__('Completion of reports')" :options="['0' => __('The appraiser completes the report'), '1' => __('The director approves the report')]" :value="$values['require_approval'] ?: '0'" span="6" :hint="__('With approval, the appraiser submits the report and only an administrator (the director) completes it.')" />
                <x-select name="report_script" :label="__('Alphabet of reports')" :options="['latn' => 'O‘zbekcha (lotin)', 'cyrl' => 'Ўзбекча (кирилл)']" :value="$values['report_script']" span="6" :hint="__('Used for new reports; each report can still be switched.')" />
                <x-textarea name="membership" :label="__('Membership certificate of the appraisers\' association')" :value="$values['membership']" rows="2" :hint="__('For example: a membership certificate No. … dated … issued by the public association of appraisers. Printed in the report.')" />
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
            <p class="field-hint" style="margin-bottom: 14px">{{ __('Fill in only real figures, for example 5+ or 300+. An empty field is not shown on the website.') }}</p>
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

    <x-card :title="__('Documents appended to reports')" icon="file-text">
        <x-slot:actions>
            <a class="btn btn-sm" href="{{ route('admin.report-texts.edit') }}"><x-icon name="pencil" :size="14" /> {{ __('Report texts') }}</a>
        </x-slot:actions>
        <p class="field-hint">{{ __('Scans of the company\'s registration certificate, membership certificate, insurance policy and appraiser certificates (JPG or PNG). They are added at the end of every valuation report.') }}</p>
        @if ($documents)
            <ul class="mini-list" style="margin-top: 12px">
                @foreach ($documents as $index => $document)
                    <li>
                        <span class="mini-icon" style="--c: var(--c-sky)"><x-icon name="image" :size="18" /></span>
                        <div class="grow"><a href="{{ route('admin.report-documents.show', $index) }}" target="_blank">{{ $document['name'] }}</a></div>
                        <form method="post" action="{{ route('admin.settings.documents.destroy', $index) }}" data-confirm="{{ __('Delete this file?') }}">
                            @csrf @method('delete')
                            <button class="btn btn-sm btn-icon" type="submit" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}"><x-icon name="trash-2" :size="15" /></button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
        <form method="post" action="{{ route('admin.settings.documents.store') }}" enctype="multipart/form-data" class="doc-upload">
            @csrf
            <input class="input" type="file" name="files[]" accept="image/jpeg,image/png,image/webp" multiple required>
            <button class="btn" type="submit"><x-icon name="upload" :size="16" /> {{ __('Upload') }}</button>
        </form>
        @error('files')<p class="field-error">{{ $message }}</p>@enderror
        @error('files.*')<p class="field-error">{{ $message }}</p>@enderror
    </x-card>

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
