<?php

namespace App\Services\Landmarks;

use App\Models\District;
use App\Models\Landmark;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Finds the landmarks (mo'ljal) of a district in OpenStreetMap: its border
 * is found with Nominatim, then the named mahallas, massifs, villages,
 * markets, metro stations, shopping centres, parks… inside it are read with
 * Overpass. They are kept in the database and read again after half a year;
 * a failed attempt is not repeated for ten minutes.
 */
final class LandmarkFinder
{
    private const REFRESH_DAYS = 180;

    private const LIMIT = 1500;

    /** Whether the landmarks of the district are known (fetched now or before). */
    public function ensure(District $district): bool
    {
        $fresh = $district->landmarks_fetched_at && now()->diffInDays($district->landmarks_fetched_at, true) < self::REFRESH_DAYS;
        if ($fresh) {
            return true;
        }
        $known = $district->landmarks_fetched_at !== null;
        $failedKey = 'landmarks:failed:'.$district->id;
        if (Cache::has($failedKey)) {
            return $known;
        }

        $found = $this->fetch($district);
        if ($found === null) {
            Cache::put($failedKey, true, now()->addMinutes(10));

            return $known;
        }

        DB::transaction(function () use ($district, $found) {
            Landmark::where('district_id', $district->id)->delete();
            foreach (array_chunk($found, 200) as $chunk) {
                Landmark::insert(array_map(fn ($row) => [...$row, 'district_id' => $district->id, 'created_at' => now(), 'updated_at' => now()], $chunk));
            }
            $district->forceFill(['landmarks_fetched_at' => now()])->save();
        });

        return true;
    }

    /**
     * @return list<array{name: string, kind: string, latitude: ?float, longitude: ?float}>|null null when the services did not answer
     */
    public function fetch(District $district): ?array
    {
        $relation = $this->border($district);
        if ($relation === null) {
            return null;
        }

        $area = 3600000000 + $relation;
        $query = <<<OVERPASS
            [out:json][timeout:90];
            area({$area})->.d;
            (
              nwr["place"~"^(suburb|quarter|neighbourhood|village|hamlet|town|locality|isolated_dwelling)$"]["name"](area.d);
              relation["boundary"="administrative"]["admin_level"~"^(8|9|10)$"]["name"](area.d);
              nwr["amenity"="marketplace"]["name"](area.d);
              nwr["railway"="station"]["name"](area.d);
              nwr["amenity"="bus_station"]["name"](area.d);
              nwr["shop"="mall"]["name"](area.d);
              nwr["amenity"~"^(hospital|university|college)$"]["name"](area.d);
              nwr["leisure"="park"]["name"](area.d);
              way["landuse"="residential"]["name"](area.d);
            );
            out center tags;
            OVERPASS;

        foreach ((array) config('fairvalue.map.overpass') as $url) {
            try {
                $response = Http::withHeaders(['User-Agent' => $this->agent()])->asForm()->timeout(100)->post($url, ['data' => $query]);
            } catch (Throwable $e) {
                report($e);

                continue;
            }
            if ($response->successful() && is_array($response->json('elements'))) {
                return $this->landmarks($response->json('elements'));
            }
        }

        return null;
    }

    /** The OpenStreetMap relation of the district's border, found by its name and its region's. */
    private function border(District $district): ?int
    {
        $district->loadMissing('region');
        foreach (array_unique([
            $district->name_uz.', '.$district->region?->name_uz,
            (string) $district->name_uz,
        ]) as $query) {
            try {
                $response = Http::withHeaders(['User-Agent' => $this->agent()])->timeout(15)->get((string) config('fairvalue.map.search'), [
                    'q' => $query, 'format' => 'jsonv2', 'limit' => 5, 'countrycodes' => 'uz',
                ]);
            } catch (Throwable $e) {
                report($e);

                return null;
            }
            if (! $response->successful()) {
                return null;
            }
            foreach ((array) $response->json() as $place) {
                if (($place['osm_type'] ?? null) === 'relation' && (($place['category'] ?? $place['class'] ?? null) === 'boundary' || ($place['type'] ?? null) === 'administrative')) {
                    return (int) $place['osm_id'];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $elements
     * @return list<array{name: string, kind: string, latitude: ?float, longitude: ?float}>
     */
    private function landmarks(array $elements): array
    {
        $found = [];
        foreach ($elements as $element) {
            $tags = (array) ($element['tags'] ?? []);
            $name = trim((string) ($tags['name:uz'] ?? $tags['name:uz-Latn'] ?? $tags['name'] ?? ''));
            $kind = self::kind($tags, $name);
            if ($name === '' || $kind === null || mb_strlen($name) > 200) {
                continue;
            }
            $key = mb_strtolower($name);
            $rank = array_search($kind, Landmark::KINDS, true);
            // One entry per name: the most telling kind wins.
            if (isset($found[$key]) && array_search($found[$key]['kind'], Landmark::KINDS, true) <= $rank) {
                continue;
            }
            $found[$key] = [
                'name' => $name,
                'kind' => $kind,
                'latitude' => isset($element['lat']) ? (float) $element['lat'] : (isset($element['center']['lat']) ? (float) $element['center']['lat'] : null),
                'longitude' => isset($element['lon']) ? (float) $element['lon'] : (isset($element['center']['lon']) ? (float) $element['center']['lon'] : null),
            ];
        }

        $found = array_values($found);
        usort($found, fn ($a, $b) => [array_search($a['kind'], Landmark::KINDS, true), mb_strtolower($a['name'])] <=> [array_search($b['kind'], Landmark::KINDS, true), mb_strtolower($b['name'])]);

        return array_slice($found, 0, self::LIMIT);
    }

    /** @param  array<string, string>  $tags */
    private static function kind(array $tags, string $name): ?string
    {
        $place = $tags['place'] ?? null;

        return match (true) {
            ($tags['boundary'] ?? null) === 'administrative', (bool) preg_match('/\b(mahalla|mahallasi|mfy)\b|махалл|маҳалла/iu', $name) => 'mahalla',
            ($tags['station'] ?? null) === 'subway' || ($tags['subway'] ?? null) === 'yes' => 'metro',
            in_array($place, ['suburb', 'quarter', 'neighbourhood'], true), ($tags['landuse'] ?? null) === 'residential' => 'area',
            in_array($place, ['village', 'hamlet', 'town', 'locality', 'isolated_dwelling'], true) => 'village',
            ($tags['amenity'] ?? null) === 'marketplace' => 'market',
            ($tags['shop'] ?? null) === 'mall' => 'mall',
            ($tags['railway'] ?? null) === 'station', ($tags['amenity'] ?? null) === 'bus_station' => 'station',
            ($tags['leisure'] ?? null) === 'park' => 'park',
            ($tags['amenity'] ?? null) === 'hospital' => 'hospital',
            in_array($tags['amenity'] ?? null, ['university', 'college'], true) => 'education',
            default => null,
        };
    }

    private function agent(): string
    {
        $contact = Setting::main('email') ?: parse_url((string) config('app.url'), PHP_URL_HOST);

        return 'FairValue/1.0 (valuation system; '.$contact.')';
    }
}
