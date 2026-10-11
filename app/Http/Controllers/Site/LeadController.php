<?php

namespace App\Http\Controllers\Site;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\LeadNotifier;
use App\Support\Services;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Request form of the website ("Ариза қолдириш").
 */
class LeadController extends Controller
{
    public function store(Request $request, LeadNotifier $notifier): RedirectResponse
    {
        // Bots fill the hidden "website" field; people never see it.
        if (filled($request->input('website'))) {
            return back()->with('lead_sent', true);
        }

        $data = $request->validateWithBag('lead', [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[\d\s()+\-]{9,}$/'],
            'service' => ['nullable', Rule::in(array_keys(Services::all()))],
            'message' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'name' => __('Your name'),
            'phone' => __('Phone number'),
            'message' => __('Message'),
        ]);

        $lead = Lead::create([
            ...$data,
            'status' => LeadStatus::New,
            'locale' => app()->getLocale(),
            'ip_address' => $request->ip(),
        ]);

        dispatch(fn () => $notifier->send($lead))->afterResponse();

        return back()->with('lead_sent', true)->withFragment('request');
    }
}
