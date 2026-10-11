@php
    use App\Support\Format;
    use App\Enums\ValuationStatus;
    $completed = $object && $object->status === ValuationStatus::Completed;
@endphp
<x-layouts.site :title="__('Check a report')" page="verify" :description="__('Check the authenticity of a valuation report by its verification code or QR code.')">
    @include('site._page-hero', ['title' => __('Check the authenticity of a report'), 'lead' => __('Enter the code printed next to the QR code on the last page of the report.')])

    <section class="section">
        <div class="container verify-page">
            <form method="get" action="{{ route('verify') }}" class="panel">
                <label for="code" style="display: block; margin-bottom: 8px; font-weight: 800">{{ __('Verification code') }}</label>
                <div class="verify-form">
                    <input id="code" class="input" name="code" value="{{ $code ?: $input }}" placeholder="FV-XXXX-XXXX" autocomplete="off" data-verify-input required>
                    <button class="btn btn-primary" type="submit"><x-icon name="scan-search" :size="18" /> {{ __('Check') }}</button>
                </div>
            </form>

            @if ($searched)
                <div class="verify-card" aria-live="polite">
                    @if (! $object)
                        <div class="verify-status verify-bad">
                            <span class="mark"><x-icon name="x" :size="26" /></span>
                            <div>
                                <h2>{{ __('Report not found') }}</h2>
                                <p>{{ __('There is no report with this code. Check the code; if it is correct, the document may be forged — contact us.') }}</p>
                            </div>
                        </div>
                    @else
                        <div @class(['verify-status', 'verify-ok' => $completed, 'verify-wait' => ! $completed])>
                            <span class="mark"><x-icon :name="$completed ? 'check' : 'clock'" :size="26" /></span>
                            <div>
                                <h2>{{ $completed ? __('The report is genuine') : __('The valuation is not completed yet') }}</h2>
                                <p>{{ $completed ? __('The report was issued by our company. Compare the details below with the document.') : __('This code belongs to a valuation that is still in progress. A final report has not been issued.') }}</p>
                            </div>
                        </div>
                        <dl class="verify-facts">
                            <div><dt>{{ __('Report number') }}</dt><dd>{{ $object->report_number ?? '—' }}</dd></div>
                            <div><dt>{{ __('Verification code') }}</dt><dd class="code" style="font-family: ui-monospace, Menlo, monospace">{{ $object->verify_code }}</dd></div>
                            <div><dt>{{ __('Valuation date') }}</dt><dd>{{ Format::date($object->valuation_date) }}</dd></div>
                            @foreach ($object->publicFacts() as $label => $value)
                                @if (filled($value))<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endif
                            @endforeach
                            @if ($object->purposeLabel())<div><dt>{{ __('Purpose of valuation') }}</dt><dd>{{ $object->purposeLabel() }}</dd></div>@endif
                            @if ($completed && $object->market_value !== null)
                                <div><dt>{{ $object instanceof \App\Models\DamageCase ? __('Total damage') : __('Market value') }}</dt><dd class="verify-value">{{ Format::money($object->market_value) }}</dd></div>
                            @endif
                            @if ($object->appraiser)<div><dt>{{ __('Appraiser') }}</dt><dd>{{ $object->appraiser->name }}@if ($object->appraiser->certificate_number) · {{ __('certificate') }} № {{ $object->appraiser->certificate_number }}@endif</dd></div>@endif
                        </dl>
                    @endif
                </div>
            @else
                <div class="panel" style="margin-top: 28px; box-shadow: none">
                    <ul class="check-list">
                        <li><x-icon name="qr-code" :size="20" /> <span>{{ __('Scan the QR code on the report with your phone camera — the result opens automatically.') }}</span></li>
                        <li><x-icon name="search" :size="20" /> <span>{{ __('Or type the code in the form above: letters and digits, for example FV-7K3M-9Q2X.') }}</span></li>
                        <li><x-icon name="shield-check" :size="20" /> <span>{{ __('Personal data of clients is never shown on this page.') }}</span></li>
                    </ul>
                </div>
            @endif
        </div>
    </section>
</x-layouts.site>
