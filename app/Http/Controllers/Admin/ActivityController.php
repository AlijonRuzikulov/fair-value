<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\User;
use App\Services\Audit\AuditChain;
use App\Support\CsvExport;
use App\Support\FieldNames;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Audit log: signing in and out, data entered and taken out, the system.
 */
class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $counts = Activity::query()
            ->where('created_at', '>=', now()->subDay())
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        return view('admin.activity', [
            'activities' => $this->query($request)->with('user')->latest('id')->paginate(40)->withQueryString(),
            'users' => User::orderBy('name')->pluck('name', 'id'),
            'events' => collect(Activity::EVENTS)->mapWithKeys(fn ($category, $event) => [$event => (new Activity(['event' => $event]))->eventLabel()])->all(),
            'category' => $request->query('category'),
            'counts' => $counts,
            'check' => AuditChain::lastCheck(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        return CsvExport::download('audit-'.now()->format('Y-m-d').'.csv', $this->query($request)->with('user')->orderBy('id'), [
            __('Time') => fn (Activity $a) => $a->created_at?->format('d.m.Y H:i:s'),
            __('Employee') => fn (Activity $a) => $a->actorLabel(),
            __('E-mail') => fn (Activity $a) => $a->user_email,
            __('Category') => fn (Activity $a) => Activity::categoryLabel($a->category),
            __('Action') => fn (Activity $a) => $a->eventLabel(),
            __('Record') => fn (Activity $a) => trim(($a->subject_type ? FieldNames::subject($a->subject_type).' · ' : '').$a->subjectText(), ' ·'),
            __('Details') => fn (Activity $a) => $a->changes ? json_encode($a->changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '',
            'IP' => fn (Activity $a) => $a->ip_address,
            __('Device') => fn (Activity $a) => $a->user_agent,
            __('Request') => fn (Activity $a) => $a->url,
            __('Hash') => fn (Activity $a) => $a->hash,
        ]);
    }

    /** Checks the hash chain now (it is also checked every night). */
    public function verify(AuditChain $chain): RedirectResponse
    {
        $report = $chain->verify();
        Activity::record('system', null, ['task' => 'audit-verify', 'ok' => $report['ok'], 'checked' => $report['checked'], 'record' => $report['broken_id']], 'Audit log integrity check');

        return $report['ok']
            ? back()->with('status', __('The audit log is intact: :count records checked.', ['count' => $report['checked']]))
            : back()->with('error', __('The audit log was changed outside the system (record #:id): :reason', ['id' => $report['broken_id'], 'reason' => __((string) $report['reason'])]));
    }

    private function query(Request $request): Builder
    {
        return Activity::query()
            ->when(in_array($request->query('category'), Activity::CATEGORIES, true), fn ($q) => $q->where('category', $request->query('category')))
            ->when($request->query('user_id'), fn ($q, $v) => $q->where('user_id', $v))
            ->when(array_key_exists((string) $request->query('event'), Activity::EVENTS), fn ($q) => $q->where('event', $request->query('event')))
            ->when($request->query('ip'), fn ($q, $v) => $q->where('ip_address', 'like', $v.'%'))
            ->when($request->query('q'), fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('subject_label', 'like', "%{$v}%")
                ->orWhere('user_email', 'like', "%{$v}%")
                ->orWhere('url', 'like', "%{$v}%")))
            ->when($request->query('from'), fn ($q, $v) => $q->where('created_at', '>=', $v.' 00:00:00'))
            ->when($request->query('to'), fn ($q, $v) => $q->where('created_at', '<=', $v.' 23:59:59'));
    }
}
