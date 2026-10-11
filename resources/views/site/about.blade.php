@php use App\Models\Setting; @endphp
<x-layouts.site :title="__('About us')" page="about">
    @include('site._page-hero', ['title' => __('About us'), 'lead' => __(':name is an independent valuation company. We help people, companies and banks make decisions based on the real value of property.', ['name' => Setting::get('name')])])

    <section class="section">
        <div class="container why">
            <div class="prose reveal">
                <h2>{{ __('Who we are') }}</h2>
                <p>{{ __('Our team consists of certified appraisers, engineers and economists. We value apartments and houses, commercial real estate, vehicles, equipment and businesses, assess damage after accidents and prepare business plans and feasibility studies.') }}</p>
                <p>{{ __('We work according to the Law of the Republic of Uzbekistan "On valuation activity" and the national valuation standards. Every report is checked by a second appraiser before it is issued.') }}</p>
                <p>{{ __('Each report receives a unique verification code and a QR code, so that a bank, a notary or a court can make sure that the document is genuine in a few seconds.') }}</p>
            </div>
            <div class="stats-grid reveal" style="grid-template-columns: repeat(2, minmax(0, 1fr))">
                <div class="stat" style="border-bottom: 1px solid var(--line)"><strong>{{ Setting::get('stat_years') }}</strong><span>{{ __('years on the market') }}</span></div>
                <div class="stat" style="border-right: 0; border-bottom: 1px solid var(--line)"><strong>{{ Setting::get('stat_reports') }}</strong><span>{{ __('reports completed') }}</span></div>
                <div class="stat"><strong>{{ Setting::get('stat_clients') }}</strong><span>{{ __('satisfied clients') }}</span></div>
                <div class="stat" style="border-right: 0"><strong>{{ Setting::get('stat_banks') }}</strong><span>{{ __('banks accept our reports') }}</span></div>
            </div>
        </div>
    </section>

    <section class="section section-soft">
        <div class="container">
            <div class="section-head center">
                <p class="eyebrow">{{ __('Principles') }}</p>
                <h2 class="section-title">{{ __('What we stand for') }}</h2>
            </div>
            <div class="values">
                <div class="value-card reveal"><span class="service-icon"><x-icon name="scale" :size="26" /></span><h3>{{ __('Independence') }}</h3><p>{{ __('The result of the valuation does not depend on who orders it. We report the value that the market confirms.') }}</p></div>
                <div class="value-card reveal"><span class="service-icon"><x-icon name="shield-check" :size="26" /></span><h3>{{ __('Responsibility') }}</h3><p>{{ __('Our professional liability is insured, and every appraiser holds a qualification certificate.') }}</p></div>
                <div class="value-card reveal"><span class="service-icon"><x-icon name="lock" :size="26" /></span><h3>{{ __('Confidentiality') }}</h3><p>{{ __('Client documents and the results of the valuation are not shared with third parties.') }}</p></div>
            </div>
            @if (Setting::get('license'))
                <p class="rates-note" style="text-align: center; margin-top: 28px"><x-icon name="badge-check" :size="16" /> {{ Setting::get('license') }}</p>
            @endif
        </div>
    </section>

    @include('site._cta')
</x-layouts.site>
