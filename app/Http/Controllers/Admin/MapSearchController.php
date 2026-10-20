<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Finds an address on the map (OpenStreetMap Nominatim by default), asked by
 * this site with a User-Agent that names it. Nominatim allows a search on
 * request, not while typing, and asks that the answers are kept: each one
 * is kept for a week.
 */
class MapSearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q'));
        if (mb_strlen($query) < 3 || mb_strlen($query) > 200) {
            return response()->json([]);
        }

        $key = 'map-search:'.md5(mb_strtolower($query));
        $places = Cache::get($key);
        if ($places === null) {
            $places = $this->find($query);
            if ($places === null) {
                return response()->json(['message' => __('The address search did not answer. Mark the point on the map or type its coordinates.')], 502);
            }
            Cache::put($key, $places, now()->addWeek());
        }

        return response()->json($places);
    }

    /** @return list<array{label: string, lat: float, lng: float}>|null null when the service did not answer */
    private function find(string $query): ?array
    {
        $contact = Setting::get('email') ?: parse_url((string) config('app.url'), PHP_URL_HOST);

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'FairValue/1.0 (valuation system; '.$contact.')',
                'Referer' => rtrim((string) config('app.url'), '/').'/',
            ])->timeout(8)->get((string) config('fairvalue.map.search'), [
                'q' => $query,
                'format' => 'jsonv2',
                'limit' => 6,
                'countrycodes' => 'uz',
                'accept-language' => 'uz,ru',
            ]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if (! $response->successful() || ! is_array($response->json())) {
            return null;
        }

        return collect($response->json())
            ->filter(fn ($place) => is_array($place) && isset($place['lat'], $place['lon'], $place['display_name']))
            ->map(fn ($place) => ['label' => (string) $place['display_name'], 'lat' => (float) $place['lat'], 'lng' => (float) $place['lon']])
            ->values()->all();
    }
}
