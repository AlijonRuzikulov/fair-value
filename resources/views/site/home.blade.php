@php
    use App\Models\Setting;
    use App\Support\Format;
    $phone = Setting::get('phone');
    $stats = [
        [Setting::get('stat_years'), __('years on the market')],
        [Setting::get('stat_reports'), __('reports completed')],
        [Setting::get('stat_clients'), __('satisfied clients')],
        [Setting::get('stat_banks'), __('banks accept our reports')],
    ];
    $faq = [
        [__('How much does a valuation cost?'), __('The price depends on the type of property, its size and the purpose of the valuation. Call us or leave a request — we will name the exact price, and it is fixed in the contract before the work starts.')],
        [__('How long does it take?'), __('An apartment, a house or a car is usually valued within 1–2 working days after the inspection. Business, equipment complexes and large objects take from 5 working days.')],
        [__('What documents are needed?'), __('As a rule, the title document, the cadastral or technical passport and the owner\'s passport. The exact list for each service is on its page; we will also send it to you after the call.')],
        [__('Do banks accept your reports?'), __('Yes. Our reports comply with the national valuation standards of Uzbekistan and are accepted by commercial banks, notaries, courts and insurance companies.')],
        [__('How can I check that a report is genuine?'), __('Every report has a QR code and a verification code. Scan the code or enter it on the "Check a report" page — you will see the report number, date and the result of the valuation.')],
        [__('Do you work outside Tashkent?'), __('Yes, our appraisers work in all regions of Uzbekistan. The inspection date is agreed with you in advance.')],
    ];
@endphp
<x-layouts.site page="home">
    {{-- Hero --}}
    <section class="hero">
        <div class="container hero-inner">
            <div>
                <span class="hero-badge"><span class="dot"><x-icon name="shield-check" :size="13" /></span> {{ __('Licensed valuation company') }}</span>
                <h1>{!! __('We determine the <em>fair value</em> of your property') !!}</h1>
                <p class="hero-lead">{{ __('Valuation of real estate, vehicles, equipment and business, damage assessment after accidents, business plans and expert opinions. Our reports are accepted by banks, notaries, courts and insurance companies.') }}</p>
                <div class="hero-actions">
                    <a class="btn btn-primary" href="#request">{{ __('Leave a request') }} <x-icon name="arrow-right" :size="18" /></a>
                    <a class="btn btn-ghost" href="{{ Format::telLink($phone) }}"><x-icon name="phone" :size="18" /> {{ $phone }}</a>
                </div>
                <div class="hero-trust">
                    <span><x-icon name="landmark" :size="18" /> {{ __('Accepted by banks') }}</span>
                    <span><x-icon name="clock" :size="18" /> {{ __('Report in 1–3 days') }}</span>
                    <span><x-icon name="qr-code" :size="18" /> {{ __('QR verification of every report') }}</span>
                </div>
            </div>

            <div class="request-card" id="request">
                <h2>{{ __('Get a consultation') }}</h2>
                <p>{{ __('Leave your phone number — the appraiser will call you back, name the price and the list of documents.') }}</p>
                @include('site._request-form', ['compact' => true, 'formId' => 'hero'])
            </div>
        </div>
    </section>

    {{-- Numbers --}}
    <section class="stats">
        <div class="container">
            <div class="stats-grid">
                @foreach ($stats as [$value, $label])
                    <div class="stat"><strong>{{ $value }}</strong><span>{{ $label }}</span></div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Services --}}
    <section class="section" id="services">
        <div class="container">
            <div class="section-head reveal">
                <p class="eyebrow">{{ __('Services') }}</p>
                <h2 class="section-title">{{ __('Dear clients, we can help you with the following services') }}</h2>
                <p class="section-lead">{{ __('All types of property valuation, business plans and feasibility studies, expert opinions on import contracts and audit opinions.') }}</p>
            </div>
            <div class="services-grid">
                @foreach ($services as $slug => $service)
                    <article class="service-card reveal" style="--c: {{ $service['color'] }}">
                        <span class="service-icon"><x-icon :name="$service['icon']" :size="26" /></span>
                        <h3>{{ $service['title'] }}</h3>
                        <p>{{ $service['short'] }}</p>
                        <a class="link-arrow stretch" href="{{ route('services.show', $slug) }}">{{ __('More') }} <x-icon name="arrow-right" :size="16" /></a>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- How we work --}}
    <section class="section section-dark">
        <div class="container">
            <div class="section-head reveal">
                <p class="eyebrow">{{ __('How we work') }}</p>
                <h2 class="section-title">{{ __('From request to report in four steps') }}</h2>
            </div>
            <div class="steps">
                <div class="step reveal"><h3>{{ __('Request') }}</h3><p>{{ __('Leave a request on the website or call us. We will tell you the price, the timing and the list of documents.') }}</p></div>
                <div class="step reveal"><h3>{{ __('Contract and inspection') }}</h3><p>{{ __('We sign a contract, and the appraiser inspects the object at a time convenient for you.') }}</p></div>
                <div class="step reveal"><h3>{{ __('Calculation') }}</h3><p>{{ __('The appraiser analyses the market and calculates the value using the cost, comparative and income approaches.') }}</p></div>
                <div class="step reveal"><h3>{{ __('Report') }}</h3><p>{{ __('You receive a signed report with a QR code that the bank can check on our website.') }}</p></div>
            </div>
        </div>
    </section>

    {{-- Why us --}}
    <section class="section">
        <div class="container why">
            <div class="reveal">
                <p class="eyebrow">{{ __('Why us') }}</p>
                <h2 class="section-title">{{ __('A valuation you can rely on') }}</h2>
                <ul class="why-list">
                    <li><span class="mark"><x-icon name="award" :size="22" /></span><div><h3>{{ __('Certified appraisers') }}</h3><p>{{ __('Every report is prepared by an appraiser with a qualification certificate; the company\'s liability is insured.') }}</p></div></li>
                    <li><span class="mark"><x-icon name="landmark" :size="22" /></span><div><h3>{{ __('Accepted everywhere') }}</h3><p>{{ __('Reports comply with the national valuation standards and are accepted by banks, notaries and courts.') }}</p></div></li>
                    <li><span class="mark"><x-icon name="handshake" :size="22" /></span><div><h3>{{ __('Transparent price') }}</h3><p>{{ __('The price is fixed in the contract before the work starts — no hidden payments.') }}</p></div></li>
                    <li><span class="mark"><x-icon name="map" :size="22" /></span><div><h3>{{ __('All over Uzbekistan') }}</h3><p>{{ __('We go to the object in any region of the country.') }}</p></div></li>
                </ul>
            </div>
            <div class="doc-visual reveal" aria-hidden="true">
                <div class="doc-back"></div>
                <div class="doc">
                    <div class="doc-head"><strong>{{ Setting::get('name') }}</strong><span>№ {{ now()->year }}/0128</span></div>
                    <h4>{{ __('Certificate of valuation results') }}</h4>
                    <div class="doc-row"><span>{{ __('Object') }}</span><b>{{ __('Non-residential building') }}</b></div>
                    <div class="doc-row"><span>{{ __('Total area') }}</span><b>323,2 {{ __('m²') }}</b></div>
                    <div class="doc-row"><span>{{ __('Purpose') }}</span><b>{{ __('Bank loan collateral') }}</b></div>
                    <div class="doc-row"><span>{{ __('Approaches') }}</span><b>{{ __('Cost') }}, {{ __('Comparative') }}</b></div>
                    <div class="doc-total"><span>{{ __('Market value') }}</span><b>3 245 000 000 {{ __('sum') }}</b></div>
                    <div class="doc-foot">
                        <div class="doc-sign"><i></i>{{ __('Appraiser') }}</div>
                        <div class="doc-qr"><div class="qr" data-qr="{{ route('verify') }}"></div>FV-7K3M-9Q2X</div>
                    </div>
                </div>
                <div class="doc-badge"><x-icon name="badge-check" :size="22" /> {{ __('The report is genuine') }}</div>
            </div>
        </div>
    </section>

    {{-- Verify --}}
    <section class="section section-soft" id="verify">
        <div class="container">
            <div class="verify-band reveal">
                <div>
                    <p class="eyebrow">{{ __('For banks and courts') }}</p>
                    <h2>{{ __('Check the authenticity of a report') }}</h2>
                    <p>{{ __('Enter the code printed next to the QR code on the last page of the report.') }}</p>
                </div>
                <form method="get" action="{{ route('verify') }}">
                    <div class="verify-form">
                        <label class="sr-only" for="verify-code">{{ __('Verification code') }}</label>
                        <input id="verify-code" class="input" name="code" placeholder="FV-XXXX-XXXX" autocomplete="off" data-verify-input required>
                        <button class="btn btn-primary" type="submit"><x-icon name="scan-search" :size="18" /> {{ __('Check') }}</button>
                    </div>
                    <p class="verify-hint">{{ __('Or simply scan the QR code with your phone camera.') }}</p>
                </form>
            </div>
        </div>
    </section>

    {{-- Exchange rates --}}
    @if ($rates->isNotEmpty())
        <section class="section" style="padding-top: 0; background: var(--bg-soft)">
            <div class="container">
                <div class="rates reveal">
                    @foreach ($rates as $rate)
                        <div class="rate">
                            <div class="rate-code">{{ $rate->nominal > 1 ? $rate->nominal.' ' : '1 ' }}{{ $rate->code }}</div>
                            <div class="rate-value">{{ Format::number($rate->rate, 2) }}</div>
                            <span @class(['rate-diff', 'up' => $rate->diff > 0, 'down' => $rate->diff < 0])>
                                @if ($rate->diff != 0)<x-icon :name="$rate->diff > 0 ? 'trending-up' : 'trending-down'" :size="13" /> {{ Format::number(abs($rate->diff), 2) }}@else {{ __('sum') }} @endif
                            </span>
                        </div>
                    @endforeach
                </div>
                <p class="rates-note">{{ __('Official rates of the Central Bank of Uzbekistan on :date.', ['date' => $rates->first()->rate_date->format('d.m.Y')]) }}</p>
            </div>
        </section>
    @endif

    {{-- FAQ --}}
    <section class="section">
        <div class="container">
            <div class="section-head center reveal">
                <p class="eyebrow">{{ __('Questions') }}</p>
                <h2 class="section-title">{{ __('Frequently asked questions') }}</h2>
            </div>
            <div class="faq">
                @foreach ($faq as $i => [$question, $answer])
                    <details class="reveal" @if ($i === 0) open @endif>
                        <summary>{{ $question }} <x-icon name="plus" :size="20" /></summary>
                        <p>{{ $answer }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Contact --}}
    <section class="section section-soft" id="contact">
        <div class="container contact-grid">
            <div class="reveal">
                <p class="eyebrow">{{ __('Contacts') }}</p>
                <h2 class="section-title">{{ __('Call us for detailed information') }}</h2>
                <ul class="contact-list">
                    <li><span class="mark tone-emerald"><x-icon name="phone" :size="20" /></span><div><small>{{ __('Phone') }}</small><a href="{{ Format::telLink($phone) }}">{{ $phone }}</a></div></li>
                    @if (Setting::get('telegram'))<li><span class="mark tone-sky"><x-icon name="send" :size="20" /></span><div><small>Telegram</small><a href="https://t.me/{{ ltrim(Setting::get('telegram'), '@') }}" target="_blank" rel="noopener">{{ '@'.ltrim(Setting::get('telegram'), '@') }}</a></div></li>@endif
                    @if (Setting::get('instagram'))<li><span class="mark tone-pink"><x-icon name="instagram" :size="20" /></span><div><small>Instagram</small><a href="https://instagram.com/{{ ltrim(Setting::get('instagram'), '@') }}" target="_blank" rel="noopener">{{ '@'.ltrim(Setting::get('instagram'), '@') }}</a></div></li>@endif
                    @if (Setting::get('email'))<li><span class="mark tone-violet"><x-icon name="mail" :size="20" /></span><div><small>{{ __('E-mail') }}</small><a href="mailto:{{ Setting::get('email') }}">{{ Setting::get('email') }}</a></div></li>@endif
                    @if (Setting::get('address'))<li><span class="mark tone-rose"><x-icon name="map-pin" :size="20" /></span><div><small>{{ __('Address') }}</small><strong>{{ Setting::get('address') }}</strong></div></li>@endif
                </ul>
            </div>
            <div class="panel reveal">
                <h2>{{ __('Leave a request') }}</h2>
                @include('site._request-form', ['formId' => 'contact'])
            </div>
        </div>
    </section>

    @push('scripts')
        <script src="{{ asset('js/vendor/qrcode.js') }}"></script>
    @endpush
</x-layouts.site>
