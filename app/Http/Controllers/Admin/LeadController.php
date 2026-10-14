<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Lead;
use App\Support\Services;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Requests ("Аризалар") left on the public website.
 */
class LeadController extends Controller
{
    public function index(Request $request): View
    {
        $status = LeadStatus::tryFrom((string) $request->query('status'));
        $term = trim((string) $request->query('q'));

        $base = Lead::query()->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
            ->where('name', 'like', "%{$term}%")
            ->orWhere('phone', 'like', "%{$term}%")
            ->orWhere('message', 'like', "%{$term}%")));

        $counts = (clone $base)->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $leads = (clone $base)
            ->when($status, fn (Builder $q) => $q->where('status', $status->value))
            ->with('handler')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.leads.index', ['leads' => $leads, 'status' => $status, 'counts' => $counts, 'services' => Services::titles()]);
    }

    public function show(Lead $lead): View
    {
        $lead->load(['handler', 'activities' => fn ($q) => $q->with('user')->where('event', '!=', 'viewed')->limit(10)]);
        Activity::recordView($lead);

        return view('admin.leads.show', ['lead' => $lead, 'services' => Services::titles()]);
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(LeadStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $lead->update([...$data, 'handled_by' => $request->user()->id]);

        return back()->with('status', __('Changes saved.'));
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        Gate::authorize('delete-records');
        $lead->delete();

        return redirect()->route('admin.leads.index')->with('status', __('Deleted.'));
    }
}
