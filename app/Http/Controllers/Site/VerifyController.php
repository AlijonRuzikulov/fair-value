<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\Registry;
use App\Support\VerifyCode;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public check of a report by the code printed on it (or its QR code).
 */
class VerifyController extends Controller
{
    public function show(Request $request): View
    {
        $input = trim((string) $request->query('code'));
        $code = VerifyCode::normalize($input);
        $object = $code !== '' ? Registry::findByVerifyCode($code) : null;
        $object?->load(['region', 'district', 'appraiser']);

        return view('site.verify', [
            'input' => $input,
            'code' => $code,
            'object' => $object,
            'searched' => $input !== '',
        ]);
    }
}
