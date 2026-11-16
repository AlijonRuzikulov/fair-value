<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Landmark;
use App\Models\RealEstate;
use App\Services\Landmarks\LandmarkFinder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The landmarks (mo'ljal) of a district for the list of the landmark box:
 * those typed before in this workspace first, then those of OpenStreetMap
 * (taken once and kept), each with its place on the map when known.
 */
class LandmarkController extends Controller
{
    public function __invoke(Request $request, LandmarkFinder $finder): JsonResponse
    {
        $district = District::find($request->integer('district_id'));
        if (! $district) {
            return response()->json(['items' => [], 'failed' => false]);
        }
        set_time_limit(180);
        $known = $finder->ensure($district);

        $items = [];
        $used = RealEstate::query()->where('district_id', $district->id)->whereNotNull('landmark')->where('landmark', '!=', '')->toBase()
            ->select('landmark', DB::raw('count(*) as uses'))->groupBy('landmark')->orderByDesc('uses')->limit(100)->pluck('landmark');
        foreach ($used as $name) {
            $items[mb_strtolower($name)] = ['name' => $name, 'kind' => Landmark::kindLabel('used'), 'lat' => null, 'lng' => null];
        }
        foreach ($district->landmarks()->orderBy('id')->get() as $landmark) {
            $key = mb_strtolower($landmark->name);
            $items[$key] ??= ['name' => $landmark->name, 'kind' => Landmark::kindLabel($landmark->kind), 'lat' => null, 'lng' => null];
            if ($items[$key]['lat'] === null && $landmark->latitude !== null) {
                $items[$key]['lat'] = $landmark->latitude;
                $items[$key]['lng'] = $landmark->longitude;
            }
        }

        return response()->json(['items' => array_values($items), 'failed' => ! $known]);
    }
}
