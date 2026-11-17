<?php

namespace App\Http\Middleware;

use App\Support\PublicSite;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pages of the public website. Without the website the home address leads
 * to the work panel (the sign-in page for a visitor) and the rest is not found.
 */
class EnsurePublicSite
{
    public function handle(Request $request, Closure $next): Response
    {
        if (PublicSite::enabled()) {
            return $next($request);
        }
        abort_unless($request->routeIs('home'), 404);

        return redirect()->route('admin.dashboard');
    }
}
