<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Districts of a region for dependent selects.
 */
class DistrictLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $districts = District::where('region_id', $request->integer('region_id'))->ordered()->get();

        return response()->json($districts->map(fn (District $d) => ['id' => $d->id, 'name' => $d->name]));
    }
}
