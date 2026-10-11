<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Region;
use App\Support\Transliterator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Directory of regions and districts.
 */
class RegionController extends Controller
{
    public function index(Request $request): View
    {
        $regions = Region::ordered()->withCount('districts')->get();
        $current = $regions->firstWhere('id', $request->integer('region')) ?? $regions->first();
        $current?->load('districts');

        return view('admin.regions', ['regions' => $regions, 'current' => $current]);
    }

    public function store(Request $request): RedirectResponse
    {
        $region = Region::create($this->names($request) + ['sort' => Region::max('sort') + 1]);

        return redirect()->route('admin.regions.index', ['region' => $region->id])->with('status', __('Saved.'));
    }

    public function update(Request $request, Region $region): RedirectResponse
    {
        $region->update($this->names($request));

        return back()->with('status', __('Changes saved.'));
    }

    public function storeDistrict(Request $request, Region $region): RedirectResponse
    {
        $region->districts()->create($this->names($request) + ['sort' => 100]);

        return back()->with('status', __('Saved.'));
    }

    public function updateDistrict(Request $request, District $district): RedirectResponse
    {
        $district->update($this->names($request));

        return back()->with('status', __('Changes saved.'));
    }

    public function destroyDistrict(District $district): RedirectResponse
    {
        // Records keep their district: a district that is in use is never removed.
        $tables = ['clients', 'real_estates', 'assets', 'vehicles', 'other_assets', 'damage_cases'];
        foreach ($tables as $table) {
            if (DB::table($table)->where('district_id', $district->id)->exists()) {
                return back()->with('error', __('The district is in use and cannot be deleted.'));
            }
        }

        $district->delete();

        return back()->with('status', __('Deleted.'));
    }

    /** Latin name is required; Cyrillic and Russian are filled from it when left empty. */
    private function names(Request $request): array
    {
        $data = $request->validate([
            'name_uz' => ['required', 'string', 'max:255'],
            'name_oz' => ['nullable', 'string', 'max:255'],
            'name_ru' => ['nullable', 'string', 'max:255'],
        ]);

        $data['name_oz'] = ($data['name_oz'] ?? null) ?: Transliterator::toCyrillic($data['name_uz']);
        $data['name_ru'] = ($data['name_ru'] ?? null) ?: $data['name_oz'];

        return $data;
    }
}
