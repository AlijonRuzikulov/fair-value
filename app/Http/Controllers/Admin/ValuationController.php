<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PersonRole;
use App\Enums\UserRole;
use App\Enums\ValuationApproach;
use App\Enums\ValuationPurpose;
use App\Enums\ValuationStatus;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Client;
use App\Models\District;
use App\Models\RealEstate;
use App\Models\Region;
use App\Models\Setting;
use App\Models\User;
use App\Models\ValuationStep;
use App\Reports\ContractPack;
use App\Reports\RealEstateReport;
use App\Reports\ReportScript;
use App\Services\Analogs\AnalogFinder;
use App\Services\Analogs\ListingAi;
use App\Services\CurrencyService;
use App\Services\Pdf\PdfPrinter;
use App\Support\CsvExport;
use App\Support\DocumentNumber;
use App\Support\Format;
use App\Support\NumberInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * List, form, card, print and export pages shared by all valuation objects.
 * Concrete controllers describe their own fields; everything about
 * location, approaches, contract, report, people and status lives here.
 */
abstract class ValuationController extends Controller
{
    /** Filters read from the query string of the list page. */
    protected const FILTERS = ['q', 'status', 'region_id', 'district_id', 'appraiser_id', 'purpose', 'from', 'to'];

    /** Money fields typed with thousand separators ("1 250 000"). */
    protected const MONEY_FIELDS = ['value_cost', 'value_comparative', 'value_income', 'market_value', 'service_fee', 'paid_amount'];

    /** @return class-string<Model> */
    abstract protected function model(): string;

    /** View folder and route name, e.g. "vehicles". */
    abstract protected function slug(): string;

    /** Validation rules of the fields specific to the object type. */
    abstract protected function rules(Request $request, ?Model $item): array;

    /** CSV columns: heading => value callback. */
    abstract protected function exportColumns(): array;

    /** Sidebar item of the page. */
    protected function nav(Request $request, ?Model $item = null): string
    {
        return $this->slug();
    }

    /*
    |--------------------------------------------------------------------------
    | Presentation
    |--------------------------------------------------------------------------
    |
    | The generic views (admin/objects/*) receive the controller as $module
    | and ask it for titles, table columns and the facts of an object.
    |
    */

    /** Title of the list page. */
    abstract public function title(): string;

    /** Name of one object, used in headings like "New …". */
    abstract public function singular(): string;

    abstract public function icon(): string;

    /**
     * Facts shown on the object card and in the printed summary.
     *
     * @return array<string, string|null>
     */
    abstract public function facts(Model $item): array;

    /**
     * Extra list columns: heading => callback returning text or HtmlString.
     *
     * @return array<string, callable>
     */
    public function columns(): array
    {
        return [];
    }

    /** Whether valuation approaches apply to this object type. */
    public function usesApproaches(): bool
    {
        return true;
    }

    /** Query parameters that keep the context (e.g. ?kind=residential). */
    public function routeParams(?Model $item = null): array
    {
        return [];
    }

    public function routeName(string $action): string
    {
        return "admin.{$this->slug()}.{$action}";
    }

    public function slugName(): string
    {
        return $this->slug();
    }

    /** Extra numeric fields that may be typed with separators. */
    protected function numericFields(): array
    {
        return [];
    }

    /** Hook for type-specific list filters. */
    protected function applyFilters(Builder $query, Request $request): void {}

    /** Hook for defaults of a new object. */
    protected function newItem(Request $request): Model
    {
        $class = $this->model();

        return new $class([
            'status' => ValuationStatus::New,
            'valuation_date' => now(),
            'appraiser_id' => $request->user()->role === UserRole::Appraiser ? $request->user()->id : null,
        ]);
    }

    /** Relations loaded for the object card and the printed summary. */
    protected function extraRelations(): array
    {
        return [];
    }

    /** Extra data for the form view. */
    protected function formData(Model $item): array
    {
        return [];
    }

    public function index(Request $request): View
    {
        $filters = $request->only(static::FILTERS);
        $base = $this->model()::query()->filter(array_diff_key($filters, ['status' => 1]));
        $this->applyFilters($base, $request);

        $statusCounts = (clone $base)->toBase()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $items = (clone $base)
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->with(['region', 'district', 'clients', 'appraiser'])
            ->withCount('attachments')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.objects.index', [
            'items' => $items,
            'filters' => $filters,
            'statusCounts' => $statusCounts,
            'nav' => $this->nav($request),
            'module' => $this,
            'districts' => self::districtOptions($filters['region_id'] ?? null),
            ...$this->sharedOptions(),
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form($request, $this->newItem($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $class = $this->model();
        $item = $this->persist(new $class, $this->validated($request, null), $request);

        return redirect()->route("admin.{$this->slug()}.show", $item->id)->with('status', __('Saved.'))->with('find_analogs', self::shouldFindAnalogs($item));
    }

    public function show(Request $request, string $id): View
    {
        $item = $this->find($id);
        $item->load([
            'region', 'district', 'appraiser', 'creator', 'clients', 'steps.user',
            'attachments.uploader', 'activities' => fn ($q) => $q->with('user')->where('event', '!=', 'viewed')->limit(15),
            ...$this->extraRelations(),
        ]);
        Activity::recordView($item);

        return view($this->showView(), [
            'item' => $item,
            'nav' => $this->nav($request, $item),
            'module' => $this,
            'usdValue' => app(CurrencyService::class)->toUsd($item->market_value),
            ...$this->showData($item),
        ]);
    }

    /** More data for the page of an object. */
    protected function showData(Model $item): array
    {
        return [];
    }

    public function edit(Request $request, string $id): View
    {
        $item = $this->find($id);
        Gate::authorize('edit-valuation', $item);
        $item->load('clients');

        return $this->form($request, $item);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $item = $this->find($id);
        Gate::authorize('edit-valuation', $item);
        $this->persist($item, $this->validated($request, $item), $request);

        return redirect()->route("admin.{$this->slug()}.show", $item->id)->with('status', __('Changes saved.'))->with('find_analogs', self::shouldFindAnalogs($item));
    }

    /**
     * The comparative approach was chosen and there are no analogues yet:
     * the object page starts the automatic search by itself.
     */
    private static function shouldFindAnalogs(Model $item): bool
    {
        return in_array($item::class, AnalogFinder::OBJECTS, true)
            && in_array(ValuationApproach::Comparative->value, (array) $item->approaches, true)
            && $item->analogs()->doesntExist()
            && Gate::allows('edit-valuation', $item)
            && app(ListingAi::class)->available();
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        Gate::authorize('delete-records');
        $item = $this->find($id);
        $item->delete();

        return redirect()->to($this->indexUrl($item))->with('status', __('Deleted.'));
    }

    public function print(string $id): View
    {
        $item = $this->find($id);
        $item->load(['region', 'district', 'appraiser', 'clients', ...$this->extraRelations()]);
        Activity::record('printed', $item);

        return view('admin.objects.print', ['item' => $item, 'module' => $this]);
    }

    /**
     * The service contract, the act of acceptance, the invoice and the
     * valuation assignment, ready to print (or to save as PDF).
     */
    public function contract(Request $request, string $id): Response
    {
        $item = $this->find($id);
        [$script, $qr, $only] = $this->contractOptions($request, $item);

        $params = fn (array $changes = []) => [$item->id, ...array_filter(['script' => $script, 'qr' => $qr ? 1 : null, 'doc' => count($only) === 1 ? $only[0] : null]), ...$changes];
        $link = fn (array $changes) => route($this->routeName('contract'), $params($changes));
        $toolbar = [
            'preparing' => __('Preparing the documents…'),
            'print' => __('Print / save as PDF'),
            'download' => ['label' => __('Download PDF'), 'url' => route($this->routeName('contract.pdf'), $params())],
            'scripts' => [
                ['label' => 'Lotin', 'url' => $link(['script' => ReportScript::LATIN]), 'active' => $script === ReportScript::LATIN],
                ['label' => 'Кирилл', 'url' => $link(['script' => ReportScript::CYRILLIC]), 'active' => $script === ReportScript::CYRILLIC],
                ['label' => __('With signature lines'), 'url' => $link(['qr' => null]), 'active' => ! $qr],
                ['label' => __('With QR codes'), 'url' => $link(['qr' => 1]), 'active' => $qr],
            ],
            'back' => ['label' => __('Back'), 'url' => route($this->routeName('show'), $item->id)],
        ];

        $html = $this->contractPage($item, $script, $qr, $only, $toolbar);
        Activity::record('printed', $item, ['document' => 'contract', 'script' => $script]);

        return response($html);
    }

    /**
     * The contract documents as a PDF file. Without Chromium on the server
     * (or when it fails) the page opens with the print dialog instead.
     */
    public function contractPdf(Request $request, string $id, PdfPrinter $printer): Response|RedirectResponse
    {
        $item = $this->find($id);
        [$script, $qr, $only] = $this->contractOptions($request, $item);
        $params = [$item->id, ...array_filter(['script' => $script, 'qr' => $qr ? 1 : null, 'doc' => count($only) === 1 ? $only[0] : null])];

        if ($printer->available()) {
            try {
                $pdf = $printer->print($this->contractPage($item, $script, $qr, $only, []));
                Activity::record('printed', $item, ['document' => 'contract', 'script' => $script, 'format' => 'pdf']);
                $name = 'shartnoma-'.(trim(preg_replace('/[^A-Za-z0-9]+/', '-', (string) ($item->contract_number ?: $item->id)), '-') ?: $item->id).($qr ? '-qr' : '').'.pdf';

                return response($pdf, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $name),
                ]);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return redirect()->route($this->routeName('contract'), [...$params, 'autoprint' => 1]);
    }

    /** @return array{string, bool, list<string>} the script, QR codes instead of signature lines, the documents */
    private function contractOptions(Request $request, Model $item): array
    {
        $script = in_array($request->query('script'), [ReportScript::LATIN, ReportScript::CYRILLIC], true)
            ? $request->query('script')
            : ($item instanceof RealEstate ? RealEstateReport::values($item)['script'] : (Setting::get('report_script') === ReportScript::CYRILLIC ? ReportScript::CYRILLIC : ReportScript::LATIN));
        $only = in_array($request->query('doc'), ContractPack::DOCUMENTS, true) ? [$request->query('doc')] : ContractPack::DOCUMENTS;

        return [$script, $request->boolean('qr'), $only];
    }

    /** @param  list<string>  $only */
    private function contractPage(Model $item, string $script, bool $qr, array $only, array $toolbar): string
    {
        // The documents are in Uzbek whatever language the panel is in.
        $locale = app()->getLocale();
        app()->setLocale('uz');
        try {
            return view('admin.report.contract', ContractPack::build($item, ReportScript::make($script), $qr, $only) + ['toolbar' => $toolbar])->render();
        } finally {
            app()->setLocale($locale);
        }
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->model()::query()->filter($request->only(static::FILTERS))
            ->with(['region', 'district', 'clients', 'appraiser'])
            ->latest('id');
        $this->applyFilters($query, $request);

        $columns = [
            '№' => fn ($i) => $i->id,
            ...$this->exportColumns(),
            __('Region') => fn ($i) => $i->region?->name,
            __('District') => fn ($i) => $i->district?->name,
            __('Address') => fn ($i) => $i->address,
            __('Persons') => fn ($i) => $i->clients->map(fn ($c) => $c->title.' ('.PersonRole::labelFor($c->pivot->role).')')->implode('; '),
            __('Purpose of valuation') => fn ($i) => $i->purposeLabel(),
            __('Approaches') => fn ($i) => collect($i->approachList())->map->short()->implode(', '),
            __('Market value') => fn ($i) => $i->market_value,
            __('Valuation date') => fn ($i) => Format::date($i->valuation_date),
            __('Contract') => fn ($i) => $i->contract_number,
            __('Service fee') => fn ($i) => $i->service_fee,
            __('Paid') => fn ($i) => $i->paid_amount,
            __('Report number') => fn ($i) => $i->report_number,
            __('Status') => fn ($i) => $i->status?->label(),
            __('Appraiser') => fn ($i) => $i->appraiser?->name,
            __('Created') => fn ($i) => Format::datetime($i->created_at),
        ];

        return CsvExport::download($this->slug().'-'.now()->format('Y-m-d').'.csv', $query, $columns);
    }

    /** The page of an object; a module may have its own. */
    protected function showView(): string
    {
        return 'admin.objects.show';
    }

    /** The form of an object; a module may have its own. */
    protected function formView(): string
    {
        return 'admin.objects.form';
    }

    protected function form(Request $request, Model $item): View
    {
        return view($this->formView(), [
            'item' => $item,
            'nav' => $this->nav($request, $item),
            'module' => $this,
            'districts' => self::districtOptions(old('region_id', $item->region_id)),
            'personRows' => $this->personRows($item),
            ...$this->sharedOptions(),
            // Only appraisers and administrators complete a valuation.
            'statuses' => $request->user()->can('finalize-valuations')
                ? ValuationStatus::options()
                : array_diff_key(ValuationStatus::options(), [ValuationStatus::Completed->value => true]),
            ...$this->formData($item),
        ]);
    }

    protected function find(string $id): Model
    {
        return $this->model()::findOrFail($id);
    }

    protected function indexUrl(Model $item): string
    {
        return route("admin.{$this->slug()}.index", $this->routeParams($item));
    }

    /**
     * Rows of the "Persons" editor, from the submitted form after a
     * validation error or from the saved object.
     *
     * @return list<array{client_id:int, role:string, title:string, sub:string}>
     */
    protected function personRows(Model $item): array
    {
        $old = old('persons');
        if (is_array($old)) {
            $ids = collect($old)->pluck('client_id')->filter()->all();
            $clients = Client::whereIn('id', $ids)->get()->keyBy('id');
            $pairs = collect($old)
                ->filter(fn ($row) => isset($clients[$row['client_id'] ?? null]))
                ->map(fn ($row) => [$clients[$row['client_id']], $row['role'] ?? PersonRole::Owner->value]);
        } elseif ($item->exists) {
            $pairs = $item->clients->map(fn (Client $c) => [$c, $c->pivot->role]);
        } else {
            // "Add an object" from a client card: ?client=ID
            $client = request()->integer('client') ? Client::find(request()->integer('client')) : null;
            $pairs = $client ? collect([[$client, PersonRole::Owner->value]]) : collect();
        }

        return $pairs->map(fn ($pair) => [
            'client_id' => $pair[0]->id,
            'role' => $pair[1],
            'title' => $pair[0]->title,
            'sub' => (string) $pair[0]->document,
        ])->values()->all();
    }

    /** District select options of a region. */
    public static function districtOptions(mixed $regionId): array
    {
        return $regionId ? District::where('region_id', $regionId)->ordered()->get()->pluck('name', 'id')->all() : [];
    }

    /** Options for selects used by both the list filters and the form. */
    protected function sharedOptions(): array
    {
        return [
            'regions' => Region::ordered()->get(),
            'appraisers' => User::appraisers()->pluck('name', 'id'),
            'statuses' => ValuationStatus::options(),
            'purposes' => ValuationPurpose::options(),
            'approachOptions' => ValuationApproach::options(),
            'personRoles' => PersonRole::options(),
        ];
    }

    protected function validated(Request $request, ?Model $item): array
    {
        $this->normalizeNumbers($request);

        return $request->validate([...$this->baseRules($request, $item), ...$this->rules($request, $item)], [], $this->attributeNames());
    }

    /** Amounts typed with spaces and a decimal comma ("1 250 000,50") become numbers. */
    protected function normalizeNumbers(Request $request): void
    {
        $numeric = [...static::MONEY_FIELDS, ...$this->numericFields()];
        $request->merge(collect($request->only($numeric))->map(fn ($v) => NumberInput::normalize($v))->all());
    }

    /** Rules of the fields every kind of object has. */
    protected function baseRules(Request $request, ?Model $item): array
    {
        return [
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'district_id' => ['nullable', 'integer', Rule::exists('districts', 'id')->where('region_id', $request->integer('region_id'))],
            'address' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', Rule::enum(ValuationPurpose::class)],
            'approaches' => ['nullable', 'array'],
            'approaches.*' => [Rule::enum(ValuationApproach::class)],
            'value_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999'],
            'value_comparative' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999'],
            'value_income' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999'],
            'market_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999'],
            'valuation_date' => ['nullable', 'date'],
            'inspection_date' => ['nullable', 'date'],
            'contract_number' => ['nullable', 'string', 'max:60'],
            'contract_date' => ['nullable', 'date'],
            'service_fee' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'report_number' => ['nullable', 'string', 'max:60'],
            'status' => ['required', Rule::enum(ValuationStatus::class)
                ->when(! self::canComplete($request->user(), $item), fn ($rule) => $rule->except([ValuationStatus::Completed]))],
            // An active appraiser or administrator (or the one already assigned).
            'appraiser_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query
                ->where(fn ($q) => $q->where('is_active', true)->whereIn('role', [UserRole::Admin->value, UserRole::Appraiser->value]))
                ->when($item?->appraiser_id, fn ($q, $current) => $q->orWhere('id', $current)))],
            'notes' => ['nullable', 'string', 'max:5000'],
            'persons' => ['nullable', 'array', 'max:20'],
            'persons.*.client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'persons.*.role' => ['nullable', Rule::enum(PersonRole::class)],
        ];
    }

    /**
     * Who may mark a valuation completed on the form: appraisers, or only the
     * director when the company requires approval. A completed one stays so.
     */
    public static function canComplete(User $user, ?Model $item): bool
    {
        if ($item?->status === ValuationStatus::Completed) {
            return true;
        }

        return $user->can(Setting::get('require_approval') ? 'approve-valuations' : 'finalize-valuations');
    }

    /**
     * A step of the approval: the appraiser submits the report, the director
     * approves or returns it, reopens it for revaluation; cancelling; the
     * customer signing the act.
     */
    public function workflow(Request $request, string $id): RedirectResponse
    {
        $item = $this->find($id);
        $action = (string) $request->input('action');
        $data = $request->validate([
            'action' => ['required', Rule::in(ValuationStep::ACTIONS)],
            'comment' => [Rule::requiredIf(in_array($action, ['returned', 'reopened', 'cancelled'], true)), 'nullable', 'string', 'max:2000'],
        ], [], ['comment' => __('Comment')]);

        $status = $item->status;
        [$gate, $possible, $next] = match ($action) {
            'submitted' => ['finalize-valuations', in_array($status, [ValuationStatus::New, ValuationStatus::InProgress], true), ValuationStatus::Review],
            'approved' => ['approve-valuations', $status === ValuationStatus::Review, ValuationStatus::Completed],
            'returned' => ['approve-valuations', $status === ValuationStatus::Review, ValuationStatus::InProgress],
            'reopened' => ['approve-valuations', $status === ValuationStatus::Completed, ValuationStatus::InProgress],
            'cancelled' => ['finalize-valuations', ! in_array($status, [ValuationStatus::Completed, ValuationStatus::Cancelled], true), ValuationStatus::Cancelled],
            'accepted' => ['finalize-valuations', $status === ValuationStatus::Completed && ! $item->currentStep('accepted'), null],
        };
        Gate::authorize($gate);
        if (! $possible) {
            return back()->with('error', __('This step is not possible in the present state of the valuation.'));
        }
        if ($action === 'submitted' && $item->market_value === null) {
            return back()->with('error', __('Enter the market value before submitting the report for approval.'));
        }

        DB::transaction(function () use ($item, $request, $data, $action, $next) {
            $item->steps()->create(['action' => $action, 'user_id' => $request->user()->id, 'comment' => $data['comment'] ?? null]);
            if ($next) {
                $item->update(['status' => $next]);
            }
        });
        Activity::record('workflow', $item, array_filter(['action' => ValuationStep::label($action), 'comment' => $data['comment'] ?? null]));

        return back()->with('status', ValuationStep::label($action).'.');
    }

    /** Human readable field names for validation messages. */
    protected function attributeNames(): array
    {
        return [
            'district_id' => __('District'),
            'market_value' => __('Market value'),
            'status' => __('Status'),
        ];
    }

    protected function persist(Model $item, array $data, Request $request): Model
    {
        return DB::transaction(function () use ($item, $data) {
            $persons = $data['persons'] ?? [];
            unset($data['persons']);

            // A value is kept only for the approaches that were applied.
            $data['approaches'] = array_values($data['approaches'] ?? []);
            foreach (ValuationApproach::cases() as $approach) {
                if (! in_array($approach->value, $data['approaches'], true)) {
                    $data[$approach->column()] = null;
                }
            }

            // A new valuation gets the next contract number (and today's date) unless one was typed.
            if (! $item->exists && blank($data['contract_number'] ?? null)) {
                $data['contract_number'] = DocumentNumber::next('contract');
                $data['contract_date'] ??= now()->toDateString();
            }

            $item->fill($data)->save();
            $item->syncPersons($persons);

            return $item;
        });
    }
}
