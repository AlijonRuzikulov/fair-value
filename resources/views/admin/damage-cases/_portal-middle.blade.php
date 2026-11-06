{{-- The car and the incident, and the calculation of the damage. --}}
<details class="portal-fold" id="object-data" open>
    <summary>{{ __('Car and incident') }}</summary>
    <fieldset class="portal-fields" @disabled(! $canEdit)>
        <div class="form-grid">
            @include('admin.damage-cases._fields')
        </div>
    </fieldset>
</details>
<details class="portal-fold" id="calculation" open>
    <summary>
        {{ __('Damage calculation') }}
        @if ($item->market_value !== null)<span class="portal-sum">{{ \App\Support\Format::money($item->market_value) }}</span>@endif
    </summary>
    <fieldset class="portal-fields portal-cards-flat" @disabled(! $canEdit)>
        @include('admin.damage-cases._form-extra')
    </fieldset>
</details>
