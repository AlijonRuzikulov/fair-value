{{-- The form of real estate in the classic look: the steps of the valuation portal and its sections, each can be folded. --}}
@php
    $params = $module->routeParams($item);
    $title = $item->exists ? $item->title : __('New').': '.mb_strtolower($module->singular());
    $back = $item->exists ? route($module->routeName('show'), $item->id) : route($module->routeName('index'), $params);
@endphp
<x-layouts.admin :title="$title" :subtitle="$item->exists ? __('Editing') : $module->title()" :nav="$nav" :icon="$module->icon()" :back="$back">
    <form method="post" class="portal" data-object-form
          action="{{ $item->exists ? route($module->routeName('update'), $item->id) : route($module->routeName('store')) }}">
        @csrf
        @if ($item->exists) @method('put') @endif

        @include('admin.real-estates._portal-head', ['headLinks' => [
            [__('Back to the list'), route($module->routeName('index'), $params), 'btn-success', 'list'],
        ]])

        @if ($errors->any())
            <div class="toast toast-error" role="alert"><x-icon name="triangle-alert" :size="18" /> {{ __('Please correct the highlighted fields.') }}</div>
        @endif

        <details class="portal-section" id="main" open>
            <summary>{{ __('Main data of the real estate') }}</summary>
            <div class="portal-body">
                <div class="form-grid">
                    @php $kind = old('kind', $item->kind?->value); @endphp
                    <div class="field span-12">
                        <span class="sr-only">{{ __('Object type') }}</span>
                        <div class="segmented" role="radiogroup" aria-label="{{ __('Object type') }}">
                            @foreach (\App\Enums\RealEstateKind::cases() as $case)
                                <label>
                                    <input type="radio" name="kind" value="{{ $case->value }}" @checked($kind === $case->value) data-kind-toggle>
                                    <span><x-icon :name="$case->icon()" :size="16" /> {{ $case->label() }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    @include('admin.real-estates._main-fields')
                </div>
                <details class="portal-sub" open>
                    <summary>{{ __('Location on the map') }}</summary>
                    <div class="form-grid">@include('admin.real-estates._map-picker', ['mapSpans' => [3, 6]])</div>
                </details>
            </div>
        </details>

        <details class="portal-section" id="amenities" open>
            <summary>{{ __('Special amenities') }}</summary>
            <div class="portal-body form-grid">
                <x-checkboxes name="features" :options="$features" :value="$item->features" />
            </div>
        </details>

        <details class="portal-section" id="persons" open>
            <summary>{{ __('Persons') }}</summary>
            <div class="portal-body">@include('admin.objects._persons')</div>
        </details>

        <details class="portal-section" id="approaches" open>
            <summary>{{ __('Approaches applied') }}</summary>
            <div class="portal-body form-grid">
                @php $applied = array_map('strval', (array) old('approaches', $item->approaches ?? [])); @endphp
                @foreach ($approachOptions as $value => $label)
                    <div class="field span-4" data-approach>
                        <label class="check">
                            <input type="checkbox" name="approaches[]" value="{{ $value }}" @checked(in_array($value, $applied, true)) data-approach-toggle>
                            <span class="check-box"><x-icon name="check" :size="14" /></span>
                            <span>{{ $label }}</span>
                        </label>
                        <x-field :name="'value_'.$value" :value="$item->{'value_'.$value}" money :suffix="__('sum')" span="12" :placeholder="__('Value by this approach')" data-approach-value />
                    </div>
                @endforeach
                <p class="field-hint span-12">{{ __('With the comparative approach, similar offers are found on the listing sites and compared on the object page after saving.') }}</p>
                <x-field name="market_value" :label="__('Market value (final)')" :value="$item->market_value" money :suffix="__('sum')" span="6" :hint="__('The result of reconciling the approaches.')" />
            </div>
        </details>

        <details class="portal-section" id="contract" open>
            <summary>{{ __('Contract, payment and status') }}</summary>
            <div class="portal-body form-grid">
                <x-field name="service_fee" :label="__('Service fee')" :value="$item->service_fee" money :suffix="__('sum')" span="3" />
                <x-field name="paid_amount" :label="__('Paid')" :value="$item->paid_amount" money :suffix="__('sum')" span="3" />
                <x-select name="status" :label="__('Status')" :options="$statuses" :value="$item->status" span="3" required />
                <x-select name="appraiser_id" :label="__('Appraiser')" :options="$appraisers" :value="$item->appraiser_id" placeholder="—" span="3" />
                <x-textarea name="notes" :label="__('Notes')" :value="$item->notes" span="12" />
            </div>
        </details>

        <div class="portal-actions portal-actions-end">
            <a class="btn btn-fill-danger" href="{{ $back }}">{{ __('Cancel') }}</a>
            <button class="btn btn-success" type="submit"><x-icon name="check" :size="16" /> {{ __('Save') }}</button>
        </div>
    </form>
</x-layouts.admin>
