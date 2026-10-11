<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(array_key_exists($locale, config('fairvalue.locales')), 404);

        $request->session()->put('locale', $locale);

        // Only return to pages of this site.
        $previous = url()->previous();

        return redirect()->to(str_starts_with($previous, url('/')) ? $previous : route('home'));
    }
}
