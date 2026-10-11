<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\Services;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PageController extends Controller
{
    public function about(): View
    {
        return view('site.about');
    }

    public function contact(): View
    {
        return view('site.contact', ['services' => Services::titles()]);
    }

    public function sitemap(): Response
    {
        $urls = [route('home'), route('services'), route('about'), route('contact'), route('verify')];
        foreach (array_keys(Services::all()) as $slug) {
            $urls[] = route('services.show', $slug);
        }

        return response()->view('site.sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml');
    }
}
