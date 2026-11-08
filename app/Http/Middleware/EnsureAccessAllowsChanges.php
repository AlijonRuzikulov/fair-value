<?php

namespace App\Http\Middleware;

use App\Support\Access;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Once the person's access has ended (the day on their account), the panel
 * is open to them for viewing only: everything can be seen, printed and
 * downloaded, nothing can be added or changed — except their own password.
 */
class EnsureAccessAllowsChanges
{
    /** Routes that work after the end. */
    private const ALWAYS = ['admin.profile.update'];

    public function handle(Request $request, Closure $next): Response
    {
        $access = Access::current();
        if ($access->allowsChanges()) {
            return $next($request);
        }

        $route = (string) $request->route()?->getName();
        $opensForm = str_ends_with($route, '.create') || str_ends_with($route, '.edit');
        if (($request->isMethodSafe() && ! $opensForm) || in_array($route, self::ALWAYS, true)) {
            return $next($request);
        }

        $message = $access->lockedMessage();
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        // Back where the person was (a form that does not open: not to itself again).
        $back = url()->previous();
        if ($request->isMethodSafe() && $back === $request->fullUrl()) {
            $back = route('admin.dashboard');
        }

        return redirect()->to($back)->with('error', $message);
    }
}
