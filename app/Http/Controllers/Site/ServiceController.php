<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\Services;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(): View
    {
        return view('site.services', ['services' => Services::all()]);
    }

    public function show(string $slug): View
    {
        $service = Services::find($slug);
        abort_unless($service, 404);

        return view('site.service', [
            'slug' => $slug,
            'service' => $service,
            'others' => collect(Services::all())->except($slug)->take(4),
        ]);
    }
}
