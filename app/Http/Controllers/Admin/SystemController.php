<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\User;
use App\Services\Audit\SystemMonitor;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * System status: health checks, configuration, security events, sessions
 * and recent errors.
 */
class SystemController extends Controller
{
    public function index(SystemMonitor $monitor): View
    {
        return view('admin.system', [
            'checks' => $monitor->checks(),
            'stats' => $monitor->stats(),
            'sessions' => $monitor->sessions(),
            'recentErrors' => $monitor->recentErrors(),
            'security' => Activity::query()
                ->whereIn('event', ['login_failed', 'login_locked', 'session_ended', 'password_changed', 'system'])
                ->with('user')->latest('id')->limit(12)->get(),
            'versions' => [
                'PHP' => PHP_VERSION,
                'Laravel' => Application::VERSION,
                __('Environment') => config('app.env'),
                __('Server time') => now()->format('d.m.Y H:i').' ('.config('app.timezone').')',
            ],
        ]);
    }

    /** Signs an employee out of one device. */
    public function endSession(Request $request, string $session): RedirectResponse
    {
        $row = DB::table('sessions')->where('id', $session)->first();
        abort_unless($row && $row->user_id, 404);

        DB::table('sessions')->where('id', $session)->delete();
        $user = User::find($row->user_id);
        Activity::record('session_ended', $user, ['reason' => 'ended_by_admin', 'ip' => $row->ip_address], $user?->name);

        return back()->with('status', __('The session has been ended.'));
    }
}
