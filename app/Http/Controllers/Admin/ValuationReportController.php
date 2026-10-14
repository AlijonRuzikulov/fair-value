<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RealEstateFeature;
use App\Enums\ValuationApproach;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\RealEstate;
use App\Reports\RealEstateReport;
use App\Reports\ReportScript;
use App\Services\CurrencyService;
use App\Services\Documents\DocumentAi;
use App\Support\ComparativeCalculator;
use App\Support\NumberInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The full valuation report of real estate: the form with the report data
 * and the printed (paginated) report.
 */
class ValuationReportController extends Controller
{
    public function edit(string $id, DocumentAi $ai): View
    {
        $item = RealEstate::with(['clients', 'attachments', 'analogs', 'appraiser', 'region', 'district'])->findOrFail($id);
        Gate::authorize('edit-valuation', $item);
        $values = RealEstateReport::values($item);

        return view('admin.real-estates.report-form', [
            'item' => $item,
            'values' => $values,
            'steps' => RealEstateReport::steps($item),
            'checklist' => RealEstateReport::checklist($item),
            'images' => RealEstateReport::attachmentRoles($item, $values),
            'scans' => $item->attachments->filter(fn ($a) => $a->isImage() || $a->mime === 'application/pdf')->sortBy('id')->values(),
            'companyDocuments' => RealEstateReport::companyDocuments(),
            'comparative' => ComparativeCalculator::calculate((float) $item->total_area, $item->analogs, RealEstateReport::comparativeOptions($item)),
            'aiAvailable' => $ai->available(),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $item = RealEstate::with(['clients', 'attachments'])->findOrFail($id);
        Gate::authorize('edit-valuation', $item);

        $request->merge(['market_value' => NumberInput::normalize($request->input('market_value'))]);
        $rules = [
            'inspection_date' => ['nullable', 'date', 'before_or_equal:today'],
            'features' => ['nullable', 'array'],
            'features.*' => [Rule::enum(RealEstateFeature::class)],
            'market_value' => ['nullable', 'numeric', 'min:0', 'max:999999999999999'],
            'script' => ['required', 'in:'.ReportScript::LATIN.','.ReportScript::CYRILLIC],
            'report_date' => ['required', 'date'],
            'building_volume' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'completion' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'scores' => ['nullable', 'array'],
            'scores.*.*' => ['nullable', 'integer', 'min:0', 'max:10'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['in:photo,document,skip'],
        ];
        foreach (RealEstateReport::FORM as $fields) {
            foreach ($fields as $field => $type) {
                $rules[$field] ??= ['nullable', 'string', 'max:'.($type === 'textarea' ? 3000 : 500)];
            }
        }
        $request->merge([
            'building_volume' => str_replace([' ', ','], ['', '.'], (string) $request->input('building_volume')) ?: null,
            'completion' => str_replace([' ', ','], ['', '.'], (string) $request->input('completion')) ?: null,
        ]);
        $data = $request->validate($rules);

        $defaults = RealEstateReport::defaults($item);
        $report = array_intersect_key((array) $item->report, ['weighting' => true]);
        foreach (RealEstateReport::FORM as $fields) {
            foreach (array_keys($fields) as $field) {
                $value = is_string($data[$field] ?? null) ? trim($data[$field]) : ($data[$field] ?? null);
                // Only what differs from the default is kept, so defaults follow later changes of the object.
                if ($value !== null && $value !== '' && (string) $value !== (string) ($defaults[$field] ?? '')) {
                    $report[$field] = $value;
                }
            }
        }

        $scores = [];
        foreach (array_keys(RealEstateReport::CRITERIA) as $i) {
            foreach (ValuationApproach::cases() as $approach) {
                $scores[$i][$approach->value] = (int) ($data['scores'][$i][$approach->value] ?? 0);
            }
        }
        if ($scores !== $defaults['scores']) {
            $report['scores'] = $scores;
        }

        $ids = $item->attachments->pluck('id')->all();
        $report['attachments'] = array_filter((array) ($data['attachments'] ?? []), fn ($role, $id) => in_array((int) $id, $ids, true), ARRAY_FILTER_USE_BOTH);
        foreach (['company_documents', 'include_macro', 'include_methodology'] as $flag) {
            $report[$flag] = $request->boolean($flag);
        }

        // Fields of the object itself (logged as ordinary changes of the object).
        // Only the fields shown on the page are changed (unticked boxes send nothing, hence "features_shown").
        $objectFields = [];
        foreach (['inspection_date', 'market_value'] as $field) {
            if ($request->exists($field)) {
                $objectFields[$field] = $data[$field] ?? null;
            }
        }
        if ($request->boolean('features_shown')) {
            $objectFields['features'] = array_values($data['features'] ?? []);
        }
        $item->fill($objectFields)->save();

        $changes = [];
        $old = (array) $item->report;
        foreach (array_unique([...array_keys($old), ...array_keys($report)]) as $key) {
            if (($old[$key] ?? null) != ($report[$key] ?? null)) {
                $changes['report.'.$key] = ['old' => $old[$key] ?? null, 'new' => $report[$key] ?? null];
            }
        }
        $item->report = $report;
        Activity::muted(fn () => $item->save());
        if ($changes) {
            Activity::record('updated', $item, $changes);
        }

        if ($request->input('then') === 'open') {
            return redirect()->route('admin.real-estates.report', $item->id);
        }

        $step = in_array($request->input('step'), array_keys(RealEstateReport::steps($item)), true) ? '#step-'.$request->input('step') : '';

        return redirect()->to(route('admin.real-estates.report.edit', $item->id).$step)->with('status', __('Changes saved.'));
    }

    public function show(Request $request, string $id): Response
    {
        $item = RealEstate::findOrFail($id);

        // Rates of the valuation date are downloaded once, if they are missing.
        $currency = app(CurrencyService::class);
        foreach (['USD', 'EUR', 'RUB'] as $code) {
            $currency->rateOn($code, $item->valuation_date ?? now());
        }

        $script = in_array($request->query('script'), [ReportScript::LATIN, ReportScript::CYRILLIC], true) ? $request->query('script') : null;

        $active = $script ?? RealEstateReport::values($item)['script'];
        $toolbar = [
            'preparing' => __('Preparing the report…'),
            'print' => __('Print / save as PDF'),
            'scripts' => [
                ['label' => 'Lotin', 'url' => route('admin.real-estates.report', [$item->id, 'script' => ReportScript::LATIN]), 'active' => $active === ReportScript::LATIN],
                ['label' => 'Кирилл', 'url' => route('admin.real-estates.report', [$item->id, 'script' => ReportScript::CYRILLIC]), 'active' => $active === ReportScript::CYRILLIC],
            ],
            'edit' => Gate::allows('edit-valuation', $item) ? ['label' => __('Report data'), 'url' => route('admin.real-estates.report.edit', $item->id)] : null,
            'back' => ['label' => __('Back'), 'url' => route('admin.real-estates.show', $item->id)],
        ];

        // The report is written in Uzbek whatever language the panel is in,
        // so it is rendered here, while the locale is switched.
        $locale = app()->getLocale();
        app()->setLocale('uz');
        try {
            $data = RealEstateReport::build($item, $script);
            $html = view('admin.report.real-estate', $data + ['toolbar' => $toolbar])->render();
        } finally {
            app()->setLocale($locale);
        }
        Activity::record('printed', $item, ['document' => 'report', 'script' => $data['s']->script]);

        return response($html);
    }

    /** A company document (certificate, insurance policy…) appended to reports. */
    public function document(int $index): StreamedResponse
    {
        $document = RealEstateReport::companyDocuments()[$index] ?? abort(404);
        abort_unless(Storage::disk('local')->exists($document['path']), 404);

        return Storage::disk('local')->response($document['path'], $document['name'], ['Cache-Control' => 'private, max-age=86400']);
    }
}
