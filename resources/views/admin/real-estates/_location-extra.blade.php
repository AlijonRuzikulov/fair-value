{{-- Landmark and the point on the map (click to mark it, drag the pin, or use the phone's location). --}}
<x-field name="landmark" :label="__('Landmark (massif, neighbourhood)')" :value="$item->landmark" span="4" list="landmarks" :placeholder="__('e.g. Feruza massif')" />
<x-field name="latitude" :label="__('Latitude')" :value="$item->latitude" span="2" inputmode="decimal" autocomplete="off" placeholder="41.311100" data-map-lat />
<x-field name="longitude" :label="__('Longitude')" :value="$item->longitude" span="2" inputmode="decimal" autocomplete="off" placeholder="69.279700" data-map-lng />
<div class="field span-4 map-tools" style="align-self: end">
    <button type="button" class="btn btn-sm" data-map-locate data-error="{{ __('The location could not be determined. Allow access to the location or mark the point on the map.') }}"><x-icon name="locate-fixed" :size="14" /> {{ __('My location') }}</button>
    <button type="button" class="btn btn-sm btn-ghost" data-map-clear><x-icon name="x" :size="14" /> {{ __('Clear') }}</button>
</div>
<div class="field span-12 map-picker" data-map-picker data-tiles="{{ config('fairvalue.map.tiles') }}" data-attribution="{{ config('fairvalue.map.attribution') }}" data-center="{{ json_encode(config('fairvalue.map.center')) }}">
    <div class="map-canvas" data-map-canvas aria-label="{{ __('Map') }}"></div>
    <p class="field-hint">{{ __('Click on the map where the object is; the pin can be dragged. Coordinates copied from Google or Yandex maps (for example 41.311, 69.279) can be pasted into "Latitude".') }}</p>
</div>
<datalist id="landmarks">@foreach ($landmarks ?? [] as $landmark)<option value="{{ $landmark }}">@endforeach</datalist>

@push('scripts')
    <link rel="stylesheet" href="{{ asset('js/vendor/leaflet/leaflet.css') }}">
    <script src="{{ asset('js/vendor/leaflet/leaflet.js') }}"></script>
@endpush
