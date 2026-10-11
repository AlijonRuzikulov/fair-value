<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Who did what and when.
 */
class ActivityController extends Controller
{
    public function __invoke(Request $request): View
    {
        $activities = Activity::query()
            ->with('user')
            ->when($request->query('user_id'), fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->query('event'), fn ($q, $v) => $q->where('event', $v))
            ->when($request->query('from'), fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->query('to'), fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.activity', [
            'activities' => $activities,
            'users' => User::orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
