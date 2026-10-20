@props(['lat', 'lng', 'zoom' => 16, 'width' => 640, 'height' => 300])
{{-- A map of tiles around the point with a pin in the middle (no JavaScript, prints as shown). On a narrow screen both sides are cut evenly. --}}
<div {{ $attributes->class('static-map') }} style="width: {{ $width }}px; height: {{ $height }}px">
    <div class="static-map-tiles" style="width: {{ $width }}px; height: {{ $height }}px; margin-left: -{{ intdiv((int) $width, 2) }}px">
        @foreach (\App\Support\StaticMap::tiles((float) $lat, (float) $lng, (int) $zoom, (int) $width, (int) $height) as $tile)
            <img src="{{ $tile['url'] }}" alt="" width="256" height="256" loading="lazy" style="left: {{ $tile['left'] }}px; top: {{ $tile['top'] }}px">
        @endforeach
    </div>
    <svg class="static-map-pin" viewBox="0 0 28 40" width="28" height="40" aria-hidden="true"><path d="M14 0C6.3 0 0 6.2 0 13.9 0 24.3 14 40 14 40s14-15.7 14-26.1C28 6.2 21.7 0 14 0z" fill="#e11d48"/><circle cx="14" cy="14" r="5.5" fill="#fff"/></svg>
    <span class="static-map-credit">{{ config('fairvalue.map.attribution') }}</span>
</div>
