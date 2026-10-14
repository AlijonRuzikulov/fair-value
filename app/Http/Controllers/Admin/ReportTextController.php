<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Setting;
use App\Reports\ReportText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Standard sections of the valuation report, edited once for every report.
 */
class ReportTextController extends Controller
{
    public function edit(): View
    {
        $texts = [];
        foreach (ReportText::titles() as $key => $title) {
            $custom = trim((string) Setting::get('report_text_'.$key, ''));
            $texts[$key] = ['title' => $title, 'value' => $custom ?: ReportText::default($key), 'custom' => $custom !== ''];
        }

        return view('admin.report-texts', ['texts' => $texts]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (ReportText::KEYS as $key) {
            $rules[$key] = ['nullable', 'string', 'max:60000'];
        }
        $data = $request->validate($rules);
        $reset = (array) $request->input('reset', []);

        $values = [];
        $changed = [];
        foreach (ReportText::KEYS as $key) {
            $text = str_replace("\r\n", "\n", trim((string) ($data[$key] ?? '')));
            // The default text (or an emptied field) is not stored, so later improvements of the default apply.
            $value = in_array($key, $reset, true) || $text === '' || $text === ReportText::default($key) ? null : $text;
            if ((string) Setting::get('report_text_'.$key, '') !== (string) $value) {
                $changed[] = $key;
            }
            $values['report_text_'.$key] = $value;
        }
        Setting::put($values);
        if ($changed) {
            Activity::record('settings', null, ['sections' => $changed], 'Report texts');
        }

        return back()->with('status', __('Settings saved.'));
    }
}
