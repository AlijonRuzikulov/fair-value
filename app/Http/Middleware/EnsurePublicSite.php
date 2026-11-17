<?php

namespace App\Http\Middleware;

use App\Support\PublicSite;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pages of the public website, the report check included. Without the
 * website nothing of it is open: every page leads to the work panel (the
 * sign-in page for a visitor).
 */
class EnsurePublicSite
{
    public function handle(Request $request, Closure $next): Response
    {
        if (PublicSite::enabled()) {
            return $next($request);
        }
        abort_unless($request->isMethod('GET') || $request->isMethod('HEAD'), 404);

        return redirect()->route('admin.dashboard');
    }
}
