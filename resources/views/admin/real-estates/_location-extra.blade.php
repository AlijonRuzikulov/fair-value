{{-- Landmark and the point on the map (click to mark it, drag the pin, or use the phone's location). --}}
<x-field name="landmark" :label="__('Landmark (massif, neighbourhood)')" :value="$item->landmark" span="4" list="landmarks" :placeholder="__('e.g. Feruza massif')" />
@include('admin.real-estates._map-picker')
<datalist id="landmarks">@foreach ($landmarks ?? [] as $landmark)<option value="{{ $landmark }}">@endforeach</datalist>
