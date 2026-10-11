<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the language chosen by the visitor (stored in the session).
 * "?lang=ru" in any URL switches the language as well, which makes links
 * to a specific language shareable.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locales = config('fairvalue.locales');

        $requested = $request->query('lang');
        if (is_string($requested) && isset($locales[$requested])) {
            $request->session()->put('locale', $requested);
        }

        $locale = $request->session()->get('locale');
        if (! isset($locales[$locale])) {
            $locale = isset($locales[config('app.locale')]) ? config('app.locale') : array_key_first($locales);
        }

        app()->setLocale($locale);
        Carbon::setLocale($locales[$locale]['carbon']);

        return $next($request);
    }
}
