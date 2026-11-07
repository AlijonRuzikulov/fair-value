<?php

namespace App\Http\Middleware;

use App\Support\License;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Without a valid licence (it ended, or no key was entered) the panel is
 * open for viewing only: everything can be seen, printed and downloaded,
 * nothing can be added or changed — except entering a new licence key,
 * one's own password and the security tools.
 */
class EnsureLicenseAllowsChanges
{
    /** Routes that work without a licence. */
    private const ALWAYS = ['admin.settings.license', 'admin.profile.update', 'admin.activity.verify', 'admin.system.sessions.destroy'];

    public function handle(Request $request, Closure $next): Response
    {
        $license = License::current();
        if ($license->allowsChanges()) {
            return $next($request);
        }

        $route = (string) $request->route()?->getName();
        $opensForm = str_ends_with($route, '.create') || str_ends_with($route, '.edit');
        if (($request->isMethodSafe() && ! $opensForm) || in_array($route, self::ALWAYS, true)) {
            return $next($request);
        }

        $message = $license->state === 'expired'
            ? __('The licence ended on :date: the system is open for viewing only.', ['date' => $license->until->format('d.m.Y')])
            : __('No valid licence key: the system is open for viewing only.');
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
