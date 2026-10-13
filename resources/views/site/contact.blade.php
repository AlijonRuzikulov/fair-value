@php use App\Models\Setting; use App\Support\Format; $phone = Setting::get('phone'); @endphp
<x-layouts.site :title="__('Contacts')" page="contact">
    @include('site._page-hero', ['title' => __('Contacts'), 'lead' => __('Call us, write to us or leave a request — we will answer all questions about the valuation.')])

    <section class="section">
        <div class="container contact-grid">
            <div>
                <ul class="contact-list" style="margin-top: 0">
                    <li><span class="mark tone-emerald"><x-icon name="phone" :size="20" /></span><div><small>{{ __('Phone') }}</small><a href="{{ Format::telLink($phone) }}">{{ $phone }}</a>@if (Setting::get('phone2'))<br><a href="{{ Format::telLink(Setting::get('phone2')) }}">{{ Setting::get('phone2') }}</a>@endif</div></li>
                    @if (Setting::get('email'))<li><span class="mark tone-violet"><x-icon name="mail" :size="20" /></span><div><small>{{ __('E-mail') }}</small><a href="mailto:{{ Setting::get('email') }}">{{ Setting::get('email') }}</a></div></li>@endif
                    @if (Setting::get('telegram'))<li><span class="mark tone-sky"><x-icon name="send" :size="20" /></span><div><small>Telegram</small><a href="https://t.me/{{ ltrim(Setting::get('telegram'), '@') }}" target="_blank" rel="noopener">{{ '@'.ltrim(Setting::get('telegram'), '@') }}</a></div></li>@endif
                    @if (Setting::get('instagram'))<li><span class="mark tone-pink"><x-icon name="instagram" :size="20" /></span><div><small>Instagram</small><a href="https://instagram.com/{{ ltrim(Setting::get('instagram'), '@') }}" target="_blank" rel="noopener">{{ '@'.ltrim(Setting::get('instagram'), '@') }}</a></div></li>@endif
                    @if (Setting::get('address'))<li><span class="mark tone-rose"><x-icon name="map-pin" :size="20" /></span><div><small>{{ __('Address') }}</small><strong>{{ Setting::get('address') }}</strong>@if (Setting::get('map_url'))<br><a class="link-arrow" style="font-size: 15px" href="{{ Setting::get('map_url') }}" target="_blank" rel="noopener">{{ __('Open on the map') }} <x-icon name="arrow-up-right" :size="15" /></a>@endif</div></li>@endif
                    @if (Setting::get('working_hours'))<li><span class="mark tone-amber"><x-icon name="clock" :size="20" /></span><div><small>{{ __('Working hours') }}</small><strong>{{ __('Mon–Sat') }}, {{ Setting::get('working_hours') }}</strong></div></li>@endif
                </ul>
                @if (Setting::get('legal_name') || Setting::get('inn'))
                    <div class="panel" style="margin-top: 28px; box-shadow: none">
                        <h3 style="font-size: 15px; font-weight: 800; margin-bottom: 10px">{{ __('Requisites') }}</h3>
                        <p style="font-size: 14px; color: var(--muted); line-height: 1.8">
                            {{ Setting::get('legal_name') }}<br>
                            @if (Setting::get('inn')){{ __('TIN') }}: {{ Setting::get('inn') }}<br>@endif
                            @if (Setting::get('bank_account')){{ __('Bank account') }}: {{ Setting::get('bank_account') }}<br>@endif
                            @if (Setting::get('bank_name')){{ Setting::get('bank_name') }}@if (Setting::get('bank_mfo')), {{ __('MFO') }} {{ Setting::get('bank_mfo') }}@endif @endif
                        </p>
                    </div>
                @endif
            </div>
            <div class="panel" id="request">
                <h2>{{ __('Leave a request') }}</h2>
                @include('site._request-form', ['formId' => 'contact'])
            </div>
        </div>
    </section>
</x-layouts.site>
