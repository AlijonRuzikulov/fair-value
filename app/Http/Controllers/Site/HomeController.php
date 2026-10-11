<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\CurrencyService;
use App\Support\Services;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(CurrencyService $currency): View
    {
        return view('site.home', ['services' => Services::all(), 'rates' => $currency->latest()]);
    }
}
