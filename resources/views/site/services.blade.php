<x-layouts.site :title="__('Services')" page="services" :description="__('Valuation of real estate, vehicles, equipment, business and damage; business plans, feasibility studies, expert opinions on import contracts and audit.')">
    @include('site._page-hero', ['title' => __('Services'), 'lead' => __('All types of property valuation, business plans and feasibility studies, expert opinions on import contracts and audit opinions.')])

    <section class="section">
        <div class="container">
            <div class="services-grid">
                @foreach ($services as $slug => $service)
                    <article class="service-card reveal" style="--c: {{ $service['color'] }}">
                        <span class="service-icon"><x-icon :name="$service['icon']" :size="26" /></span>
                        <h3>{{ $service['title'] }}</h3>
                        <p>{{ $service['short'] }}</p>
                        <p style="flex: 0; font-size: 13px; font-weight: 700; color: color-mix(in srgb, var(--c) 78%, #111735)"><x-icon name="clock" :size="14" /> {{ $service['term'] }}</p>
                        <a class="link-arrow stretch" href="{{ route('services.show', $slug) }}">{{ __('More') }} <x-icon name="arrow-right" :size="16" /></a>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    @include('site._cta')
</x-layouts.site>
