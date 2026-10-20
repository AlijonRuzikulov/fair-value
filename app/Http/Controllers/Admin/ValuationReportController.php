<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RealEstateFeature;
use App\Enums\ValuationApproach;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\RealEstate;
use App\Reports\InspectionAct;
use App\Reports\RealEstateReport;
use App\Reports\ReportScript;
use App\Services\CurrencyService;
use App\Services\Documents\DocumentAi;
use App\Services\Pdf\PdfPrinter;
use App\Support\ComparativeCalculator;
use App\Support\NumberInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

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
            'act' => ['nullable', 'array'],
            'act.participants' => ['nullable', 'string', 'max:500'],
            'act.defects' => ['nullable', 'string', 'max:3000'],
            'act.elements' => ['nullable', 'array'],
            'act.elements.*.condition' => ['nullable', Rule::in(array_keys(InspectionAct::CONDITIONS))],
            'act.elements.*.share' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'act.elements.*.wear' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'act.elements.*.description' => ['nullable', 'string', 'max:300'],
        ];
        foreach (RealEstateReport::FORM as $fields) {
            foreach ($fields as $field => $type) {
                $rules[$field] ??= ['nullable', 'string', 'max:'.($type === 'textarea' ? 3000 : 500)];
            }
        }
        $decimal = fn ($value) => str_replace([' ', ','], ['', '.'], (string) $value) ?: null;
        $request->merge([
            'building_volume' => $decimal($request->input('building_volume')),
            'completion' => $decimal($request->input('completion')),
        ]);
        if (is_array($request->input('act.elements'))) {
            $request->merge(['act' => [...(array) $request->input('act'), 'elements' => array_map(
                fn ($element) => is_array($element) ? ['share' => $decimal($element['share'] ?? null), 'wear' => $decimal($element['wear'] ?? null)] + $element : $element,
                $request->input('act.elements'),
            )]]);
        }
        $data = $request->validate($rules);

        $defaults = RealEstateReport::defaults($item);
        // What this form writes is written anew; the rest (the weighting of the analogues,
        // the inputs of the cost and income approaches…) is kept as it is.
        $managed = [...array_merge(...array_map('array_keys', array_values(RealEstateReport::FORM))), 'scores', 'attachments', 'company_documents', 'include_macro', 'include_methodology', 'act'];
        $report = array_diff_key((array) $item->report, array_flip($managed));
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
        if (isset($data['act'])) {
            $act = $this->act($data['act']);
            if ($act !== InspectionAct::defaults()) {
                $report['act'] = $act;
            }
        } elseif (isset($item->report['act'])) {
            $report['act'] = $item->report['act'];
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
        $script = self::script($request);
        $qr = ! $request->has('qr') || $request->boolean('qr');

        $active = $script ?? RealEstateReport::values($item)['script'];
        $link = fn (array $changes) => route('admin.real-estates.report', [$item->id, ...self::query($script, $qr), ...$changes]);
        $toolbar = [
            'preparing' => __('Preparing the report…'),
            'print' => __('Print / save as PDF'),
            'download' => ['label' => __('Download PDF'), 'url' => route('admin.real-estates.report.pdf', [$item->id, ...self::query($script, $qr)])],
            'scripts' => [
                ['label' => 'Lotin', 'url' => $link(['script' => ReportScript::LATIN]), 'active' => $active === ReportScript::LATIN],
                ['label' => 'Кирилл', 'url' => $link(['script' => ReportScript::CYRILLIC]), 'active' => $active === ReportScript::CYRILLIC],
                ['label' => __('With QR codes'), 'url' => $link(['qr' => null]), 'active' => $qr],
                ['label' => __('Without QR codes'), 'url' => $link(['qr' => 0]), 'active' => ! $qr],
            ],
            'edit' => Gate::allows('edit-valuation', $item) ? ['label' => __('Report data'), 'url' => route('admin.real-estates.report.edit', $item->id)] : null,
            'back' => ['label' => __('Back'), 'url' => route('admin.real-estates.show', $item->id)],
        ];

        [$html, $data] = $this->render($item, $script, $qr, $toolbar);
        Activity::record('printed', $item, ['document' => 'report', 'script' => $data['s']->script]);

        return response($html);
    }

    /** The act of inspection as entered, in the order of the elements; an empty share is the typical one. */
    private function act(array $input): array
    {
        $text = fn ($value) => is_string($value) && trim($value) !== '' ? trim($value) : null;
        $act = ['participants' => $text($input['participants'] ?? null), 'defects' => $text($input['defects'] ?? null), 'elements' => []];
        foreach (InspectionAct::ELEMENTS as $key => [, $share, $field]) {
            $element = (array) ($input['elements'][$key] ?? []);
            $act['elements'][$key] = [
                'condition' => $text($element['condition'] ?? null),
                'share' => isset($element['share']) && $element['share'] !== null ? (float) $element['share'] : (float) $share,
                'wear' => isset($element['wear']) && $element['wear'] !== null ? (float) $element['wear'] : null,
                'description' => $field === null ? $text($element['description'] ?? null) : null,
            ];
        }

        return $act;
    }

    /**
     * The report as a PDF file. Without Chromium on the server (or when it
     * fails) the report page opens with the print dialog instead.
     */
    public function pdf(Request $request, string $id, PdfPrinter $printer): Response|RedirectResponse
    {
        $item = RealEstate::findOrFail($id);
        $script = self::script($request);
        $qr = ! $request->has('qr') || $request->boolean('qr');

        if ($printer->available()) {
            try {
                [$html, $data] = $this->render($item, $script, $qr, []);
                $pdf = $printer->print($html);
                Activity::record('printed', $item, ['document' => 'report', 'script' => $data['s']->script, 'format' => 'pdf']);
                $name = 'hisobot-'.(trim(preg_replace('/[^A-Za-z0-9]+/', '-', (string) ($item->report_number ?: $item->id)), '-') ?: $item->id).($qr ? '' : '-qrsiz').'.pdf';

                return response($pdf, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $name),
                ]);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('admin.real-estates.report', [$item->id, ...self::query($script, $qr), 'autoprint' => 1]);
    }

    /** @return array<string, string|int> the script and "without QR codes" of the address of the report */
    private static function query(?string $script, bool $qr): array
    {
        return array_filter(['script' => $script, 'qr' => $qr ? null : 0], fn ($value) => $value !== null);
    }

    private static function script(Request $request): ?string
    {
        return in_array($request->query('script'), [ReportScript::LATIN, ReportScript::CYRILLIC], true) ? $request->query('script') : null;
    }

    /** @return array{string, array<string, mixed>} the page and the data of the report */
    private function render(RealEstate $item, ?string $script, bool $qr, array $toolbar): array
    {
        // Rates of the valuation date are downloaded once, if they are missing.
        $currency = app(CurrencyService::class);
        foreach (['USD', 'EUR', 'RUB'] as $code) {
            $currency->rateOn($code, $item->valuation_date ?? now());
        }

        // The report is written in Uzbek whatever language the panel is in,
        // so it is rendered here, while the locale is switched.
        $locale = app()->getLocale();
        app()->setLocale('uz');
        try {
            $data = RealEstateReport::build($item, $script);
            $html = view('admin.report.real-estate', $data + ['toolbar' => $toolbar, 'qr' => $qr])->render();
        } finally {
            app()->setLocale($locale);
        }

        return [$html, $data];
    }

    /** A company document (certificate, insurance policy…) appended to reports. */
    public function document(int $index): StreamedResponse
    {
        $document = RealEstateReport::companyDocuments()[$index] ?? abort(404);
        abort_unless(Storage::disk('local')->exists($document['path']), 404);

        return Storage::disk('local')->response($document['path'], $document['name'], ['Cache-Control' => 'private, max-age=86400']);
    }
}
