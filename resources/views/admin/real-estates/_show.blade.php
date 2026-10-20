@php $features = $item->featureList(); @endphp
@if ($features)
    <x-card :title="__('Special amenities')" icon="sparkles">
        <div class="chips">
            @foreach ($features as $feature)
                <span class="chip chip-brand"><x-icon name="check" :size="13" /> {{ $feature->label() }}</span>
            @endforeach
        </div>
    </x-card>
@endif
@if ($item->hasLocation())
    <x-card :title="__('On the map')" icon="map-pinned">
        <div class="object-map">
            <x-static-map :lat="$item->latitude" :lng="$item->longitude" :width="760" :height="280" />
            <div class="map-links">
                <span class="muted">{{ number_format($item->latitude, 6, '.', '') }}, {{ number_format($item->longitude, 6, '.', '') }}@if ($item->landmark) · {{ $item->landmark }}@endif</span>
                @foreach (\App\Support\StaticMap::links($item->latitude, $item->longitude) as $service => $url)
                    <a class="btn btn-sm" href="{{ $url }}" target="_blank" rel="noopener noreferrer"><x-icon name="external-link" :size="14" /> {{ $service }}</a>
                @endforeach
            </div>
        </div>
    </x-card>
@endif
