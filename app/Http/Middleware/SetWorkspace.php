<?php

namespace App\Http\Middleware;

use App\Support\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin panel works in the workspace of the signed-in person's
 * organisation (see App\Support\Tenant). The workspace stays set until the
 * response has been sent, so that downloads written while sending stay in it.
 */
class SetWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user) {
            $organization = $user->organization_id ? $user->organization()->first() : null;
            // Someone of an organisation that is no longer there never falls into the main workspace.
            abort_if($user->organization_id && ! $organization, 403);
            Tenant::set($organization);
            app()->terminating(fn () => Tenant::clear());
        }

        return $next($request);
    }
}
