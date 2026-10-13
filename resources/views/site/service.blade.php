<x-layouts.site :title="$service['title']" page="services" :description="$service['short']">
    @include('site._page-hero', ['title' => $service['title'], 'lead' => $service['short'], 'crumbs' => [[__('Services'), route('services')]]])

    <section class="section">
        <div class="container article-grid">
            <article class="prose" style="--c: {{ $service['color'] }}">
                <h2>{{ __('About the service') }}</h2>
                <p>{{ $service['text'] }}</p>

                <h2>{{ __('What we value') }}</h2>
                <ul class="check-list two">
                    @foreach ($service['items'] as $line)
                        <li><x-icon name="circle-check" :size="20" /> <span>{{ $line }}</span></li>
                    @endforeach
                </ul>

                <h2>{{ __('Documents') }}</h2>
                <ul class="check-list">
                    @foreach ($service['documents'] as $line)
                        <li><x-icon name="file-text" :size="20" /> <span>{{ $line }}</span></li>
                    @endforeach
                </ul>
                <p style="margin-top: 14px; font-size: 15px; color: var(--muted)">{{ __('The final list depends on the object and the purpose of the valuation; the appraiser will clarify it during the call.') }}</p>

                <div class="term-box">
                    <x-icon name="clock" :size="28" />
                    <div>{{ __('Term') }}<strong>{{ $service['term'] }}</strong></div>
                </div>
            </article>

            <aside class="panel sticky" id="request">
                <h2>{{ __('Order the service') }}</h2>
                <p style="margin-top: 6px; color: var(--muted)">{{ __('We will call you back, name the price and agree on the inspection date.') }}</p>
                @include('site._request-form', ['service' => $slug, 'formId' => 'service'])
            </aside>
        </div>
    </section>

    <section class="section section-soft">
        <div class="container">
            <div class="section-head">
                <p class="eyebrow">{{ __('Services') }}</p>
                <h2 class="section-title">{{ __('Other services') }}</h2>
            </div>
            <div class="services-grid">
                @foreach ($others as $otherSlug => $other)
                    <article class="service-card" style="--c: {{ $other['color'] }}">
                        <span class="service-icon"><x-icon :name="$other['icon']" :size="26" /></span>
                        <h3>{{ $other['title'] }}</h3>
                        <p>{{ $other['short'] }}</p>
                        <a class="link-arrow stretch" href="{{ route('services.show', $otherSlug) }}">{{ __('More') }} <x-icon name="arrow-right" :size="16" /></a>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
</x-layouts.site>
