<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Support\Registry;
use App\Support\VerifyCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        // Who checks reports (a bank, a notary…) is kept in the audit log; once per address and code an hour.
        if ($code !== '' && Cache::add('audit:verify:'.$request->ip().':'.$code, true, now()->addHour())) {
            Activity::record('verified', $object, ['code' => $code, 'found' => $object !== null], $object ? null : $code);
        }

        return view('site.verify', [
            'input' => $input,
            'code' => $code,
            'object' => $object,
            'searched' => $input !== '',
        ]);
    }
}
