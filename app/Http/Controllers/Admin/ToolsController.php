<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CurrencyService;
use Illuminate\View\View;

/**
 * "Баҳолаш плюс" — calculators for the three valuation approaches.
 * Everything is calculated in the browser (public/js/tools.js).
 */
class ToolsController extends Controller
{
    public function __invoke(CurrencyService $currency): View
    {
        return view('admin.tools', ['usd' => $currency->usd()]);
    }
}
