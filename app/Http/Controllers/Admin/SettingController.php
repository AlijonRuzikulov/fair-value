<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Setting;
use App\Reports\RealEstateReport;
use App\Reports\ReportScript;
use App\Services\CurrencyService;
use App\Support\NumberInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        'legal_name_ru', 'oked', 'membership', 'report_city', 'report_script',
    ];

    public function edit(CurrencyService $currency): View
    {
        $values = collect(self::KEYS)->mapWithKeys(fn ($key) => [$key => Setting::get($key)])->all();

        return view('admin.settings', ['values' => $values, 'rates' => $currency->latest(), 'documents' => RealEstateReport::companyDocuments()]);
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
            'legal_name_ru' => ['nullable', 'string', 'max:255'],
            'oked' => ['nullable', 'string', 'max:20'],
            'membership' => ['nullable', 'string', 'max:1000'],
            'report_city' => ['nullable', 'string', 'max:80'],
            'report_script' => ['nullable', 'in:'.ReportScript::LATIN.','.ReportScript::CYRILLIC],
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

    /** Certificates, the insurance policy and other company documents appended to every report. */
    public function storeDocuments(Request $request): RedirectResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'max:10'],
            'files.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], [], ['files' => __('Files'), 'files.*' => __('File')]);

        $documents = RealEstateReport::companyDocuments();
        foreach ($request->file('files') as $file) {
            $documents[] = [
                'name' => Str::limit($file->getClientOriginalName(), 120, ''),
                'path' => $file->storeAs('company', Str::uuid().'.'.strtolower($file->extension() ?: 'jpg'), 'local'),
            ];
        }
        Setting::put(['report_documents' => json_encode(array_slice($documents, 0, 30), JSON_UNESCAPED_UNICODE)]);
        Activity::record('uploaded', null, ['files' => array_map(fn ($f) => $f->getClientOriginalName(), $request->file('files'))], 'Company documents');

        return back()->with('status', trans_choice('{1} File uploaded.|[2,*] :count files uploaded.', count($request->file('files'))));
    }

    public function destroyDocument(int $index): RedirectResponse
    {
        $documents = RealEstateReport::companyDocuments();
        $document = $documents[$index] ?? abort(404);
        Storage::disk('local')->delete($document['path']);
        unset($documents[$index]);
        Setting::put(['report_documents' => json_encode(array_values($documents), JSON_UNESCAPED_UNICODE)]);
        Activity::record('file_deleted', null, ['file' => $document['name']], 'Company documents');

        return back()->with('status', __('File deleted.'));
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
