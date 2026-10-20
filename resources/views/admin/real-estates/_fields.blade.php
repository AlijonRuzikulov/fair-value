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
<x-field name="name" :label="__('Object name')" :value="$item->name" span="6" required :placeholder="__('e.g. Non-residential building (shop)')" />
<x-field name="cadastral_number" :label="__('Cadastral number')" :value="$item->cadastral_number" span="3" placeholder="10:01:05:01:02:0123" />
<x-field name="year_built" type="number" :label="__('Year built')" :value="$item->year_built" span="3" min="1800" />
<x-field name="total_area" :label="__('Total area')" :value="$item->total_area" money :suffix="__('m²')" span="3" />
<x-field name="useful_area" :label="__('Useful area')" :value="$item->useful_area" money :suffix="__('m²')" span="3" />
<div class="field span-3" data-kind-only="residential" @if ($kind !== 'residential') hidden @endif>
    <x-field name="living_area" :label="__('Living area')" :value="$item->living_area" money :suffix="__('m²')" span="12" />
</div>
<x-field name="land_area" :label="__('Land area')" :value="$item->land_area" money :suffix="__('m²')" span="3" />
<x-field name="built_up_area" :label="__('Built-up area')" :value="$item->built_up_area" money :suffix="__('m²')" span="3" :hint="__('Area under the building (footprint).')" />
<x-field name="cadastral_value" :label="__('Cadastral or book value')" :value="$item->cadastral_value" money :suffix="__('sum')" span="3" />
<x-field name="floors_total" type="number" :label="__('Number of floors')" :value="$item->floors_total" span="2" min="0" />
<x-field name="floor" type="number" :label="__('Floor')" :value="$item->floor" span="2" />
<x-field name="rooms" type="number" :label="__('Rooms')" :value="$item->rooms" span="2" min="0" />
<x-field name="wall_material" :label="__('Wall material')" :value="$item->wall_material" span="3" :placeholder="__('Brick, panel, monolith…')" list="wall-materials" />
<x-field name="condition" :label="__('Technical condition')" :value="$item->condition" span="3" list="conditions" />
<datalist id="wall-materials"><option value="{{ __('Brick') }}"><option value="{{ __('Panel') }}"><option value="{{ __('Monolith') }}"><option value="{{ __('Block') }}"><option value="{{ __('Wood') }}"></datalist>
<datalist id="conditions"><option value="{{ __('Excellent') }}"><option value="{{ __('Good') }}"><option value="{{ __('Satisfactory') }}"><option value="{{ __('Needs repair') }}"></datalist>
<x-checkboxes name="features" :label="__('Special amenities')" :options="$features" :value="$item->features" />
