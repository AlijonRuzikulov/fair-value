<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ValuationHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shown under a passport, PINFL, TIN, cadastral number, VIN or car number
 * while it is typed: where and when it was valued before in another
 * organisation (who and when only).
 */
class HistoryController extends Controller
{
    public function __invoke(Request $request, ValuationHistory $history): JsonResponse
    {
        $data = $request->validate([
            'field' => ['required', 'in:'.implode(',', ValuationHistory::FIELDS)],
            'value' => ['required', 'string', 'max:40'],
        ]);

        return response()->json(['items' => $history->find($data['field'], $data['value'])]);
    }
}
