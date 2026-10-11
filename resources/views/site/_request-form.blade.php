{{-- Website request form. $compact hides the message field. --}}
@php
    $compact = $compact ?? false;
    $selected = old('service', $service ?? request('service'));
    $lead = $errors->getBag('lead');
@endphp
@if (session('lead_sent'))
    <div class="alert alert-success" role="status">
        <x-icon name="circle-check" :size="22" />
        <div>{{ __('Thank you! Your request has been received.') }}<p>{{ __('We will call you back during working hours.') }}</p></div>
    </div>
@else
    <form method="post" action="{{ route('lead.store') }}" class="form" novalidate>
        @csrf
        <div class="hp" aria-hidden="true">
            <label for="website-{{ $formId ?? 'f' }}">Website</label>
            <input id="website-{{ $formId ?? 'f' }}" type="text" name="website" tabindex="-1" autocomplete="off">
        </div>
        <div class="form-row">
            <div>
                <label for="name-{{ $formId ?? 'f' }}">{{ __('Your name') }}</label>
                <input id="name-{{ $formId ?? 'f' }}" class="input @if ($lead->has('name')) is-invalid @endif" name="name" value="{{ old('name') }}" required autocomplete="name" maxlength="120">
                @if ($lead->has('name'))<p class="field-error">{{ $lead->first('name') }}</p>@endif
            </div>
            <div>
                <label for="phone-{{ $formId ?? 'f' }}">{{ __('Phone number') }}</label>
                <input id="phone-{{ $formId ?? 'f' }}" class="input @if ($lead->has('phone')) is-invalid @endif" type="tel" name="phone" value="{{ old('phone', '+998 ') }}" required autocomplete="tel" maxlength="30" inputmode="tel">
                @if ($lead->has('phone'))<p class="field-error">{{ $lead->first('phone') }}</p>@endif
            </div>
        </div>
        <div>
            <label for="service-{{ $formId ?? 'f' }}">{{ __('Service') }}</label>
            <select id="service-{{ $formId ?? 'f' }}" class="input" name="service">
                <option value="">{{ __('Choose a service') }}</option>
                @foreach (\App\Support\Services::titles() as $slug => $title)
                    <option value="{{ $slug }}" @selected($selected === $slug)>{{ $title }}</option>
                @endforeach
            </select>
        </div>
        @unless ($compact)
            <div>
                <label for="message-{{ $formId ?? 'f' }}">{{ __('Message') }} <span style="font-weight: 500; color: var(--muted)">({{ __('optional') }})</span></label>
                <textarea id="message-{{ $formId ?? 'f' }}" class="input" name="message" rows="3" maxlength="1000" placeholder="{{ __('What needs to be valued and for what purpose?') }}">{{ old('message') }}</textarea>
            </div>
        @endunless
        <button class="btn btn-primary btn-block" type="submit">{{ __('Send request') }} <x-icon name="arrow-right" :size="18" /></button>
        <p class="form-note"><x-icon name="lock" :size="15" /> {{ __('We use your data only to contact you about the request.') }}</p>
    </form>
@endif
