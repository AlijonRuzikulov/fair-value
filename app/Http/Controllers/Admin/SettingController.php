<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Setting;
use App\Services\CurrencyService;
use App\Support\NumberInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Company details shown on the website and printed documents.
 */
class SettingController extends Controller
{
    public const KEYS = [
        'name', 'legal_name', 'director', 'license', 'inn', 'phone', 'phone2', 'email', 'address',
        'working_hours', 'telegram', 'instagram', 'map_url', 'bank_name', 'bank_account', 'bank_mfo',
        'stat_years', 'stat_reports', 'stat_clients', 'stat_banks', 'labor_rate',
    ];

    public function edit(CurrencyService $currency): View
    {
        $values = collect(self::KEYS)->mapWithKeys(fn ($key) => [$key => Setting::get($key)])->all();

        return view('admin.settings', ['values' => $values, 'rates' => $currency->latest()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->merge(['labor_rate' => NumberInput::normalize($request->input('labor_rate'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'director' => ['nullable', 'string', 'max:255'],
            'license' => ['nullable', 'string', 'max:255'],
            'inn' => ['nullable', 'string', 'max:20'],
            'phone' => ['required', 'string', 'max:30'],
            'phone2' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'working_hours' => ['nullable', 'string', 'max:120'],
            'telegram' => ['nullable', 'string', 'max:120'],
            'instagram' => ['nullable', 'string', 'max:120'],
            'map_url' => ['nullable', 'url:http,https', 'max:1000'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account' => ['nullable', 'string', 'max:30'],
            'bank_mfo' => ['nullable', 'string', 'max:10'],
            'stat_years' => ['nullable', 'string', 'max:20'],
            'stat_reports' => ['nullable', 'string', 'max:20'],
            'stat_clients' => ['nullable', 'string', 'max:20'],
            'stat_banks' => ['nullable', 'string', 'max:20'],
            'labor_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        $changes = [];
        foreach ($data as $key => $value) {
            $old = Setting::get($key);
            if ((string) $old !== (string) $value) {
                $changes[$key] = ['old' => $old, 'new' => $value];
            }
        }
        Setting::put($data);
        if ($changes) {
            Activity::record('settings', null, $changes, 'Settings');
        }

        return back()->with('status', __('Settings saved.'));
    }

    public function refreshRates(CurrencyService $currency): RedirectResponse
    {
        try {
            $count = $currency->fetch();

            return back()->with('status', __(':count exchange rates updated.', ['count' => $count]));
        } catch (Throwable) {
            return back()->with('error', __('The Central Bank website is not available. Try again later.'));
        }
    }
}
