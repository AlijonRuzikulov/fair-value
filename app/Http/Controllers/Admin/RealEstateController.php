<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DocumentKind;
use App\Enums\PersonRole;
use App\Enums\RealEstateFeature;
use App\Enums\RealEstateKind;
use App\Enums\ValuationApproach;
use App\Enums\ValuationPurpose;
use App\Enums\ValuationStatus;
use App\Models\RealEstate;
use App\Reports\RealEstateReport;
use App\Reports\Survey;
use App\Support\AdminTheme;
use App\Support\Format;
use App\Support\Tenant;
use App\Support\Transliterator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "Янги кўчмас мулк" — non-residential (нотурар жой) and residential
 * (турар жой) property. The kind is taken from ?kind= on list and create
 * pages and from the record itself afterwards.
 */
class RealEstateController extends ValuationController
{
    /** Report data typed together with the main data of the object; kept with the report. */
    public const REPORT_FIELDS = [
        'property_type', 'architecture', 'function', 'repair', 'completion', 'light', 'detached', 'identification',
        'letter_number', 'value_type', 'ownership_form', 'land_rights', 'amenities',
    ];

    /** The fields of the act window of the valuation portal. */
    private const ACT_FIELDS = ['function', 'architecture', 'amenities', 'repair', 'completion', 'ownership_form', 'land_rights'];

    /** The data saved on the object page (the first step, as on the valuation portal). */
    private const MAIN_FIELDS = [
        'owner_id', 'persons', 'persons.*.client_id', 'persons.*.role', 'approaches', 'approaches.*', 'purpose',
        'name', 'cadastral_number', 'features', 'features.*', 'amenities', 'amenities.*', 'appraiser_id', 'service_fee', 'paid_amount',
        'latitude', 'longitude', 'cadastral_value', 'region_id', 'district_id', 'landmark', 'address',
        'contract_number', 'contract_date', 'report_number', 'land_area', 'built_up_area', 'useful_area', 'total_area',
        'living_area', 'rooms', 'wall_material', 'condition', 'floors_total', 'floor', 'year_built',
        'valuation_date', 'inspection_date', 'notes', ...self::REPORT_EXTRA, ...self::REPORT_FIELDS,
    ];

    /** Form fields that only steer the report fields: the kind of value and the price index. */
    private const REPORT_EXTRA = ['value_kind', 'use_index', 'market_index'];

    private RealEstateKind $kind = RealEstateKind::NonResidential;

    protected function model(): string
    {
        return RealEstate::class;
    }

    protected function slug(): string
    {
        return 'real-estates';
    }

    private function resolveKind(Request $request, ?Model $item = null): RealEstateKind
    {
        return $this->kind = $item?->kind
            ?? RealEstateKind::tryFrom((string) $request->input('kind'))
            ?? RealEstateKind::NonResidential;
    }

    protected function nav(Request $request, ?Model $item = null): string
    {
        return 'realestate.'.$this->resolveKind($request, $item)->value;
    }

    public function title(): string
    {
        return $this->kind === RealEstateKind::Residential
            ? __('List of new real estate (residential)')
            : __('List of new real estate (non-residential)');
    }

    public function singular(): string
    {
        return $this->kind === RealEstateKind::Residential ? __('Residential property') : __('Non-residential property');
    }

    public function icon(): string
    {
        return $this->kind->icon();
    }

    public function routeParams(?Model $item = null): array
    {
        return ['kind' => ($item?->kind ?? $this->kind)->value];
    }

    protected function applyFilters(Builder $query, Request $request): void
    {
        $query->where('kind', $this->resolveKind($request)->value);

        if ($cadastral = trim((string) $request->query('cadastral'))) {
            $query->where('cadastral_number', 'like', "%{$cadastral}%");
        }
    }

    protected function newItem(Request $request): Model
    {
        $item = parent::newItem($request);
        $item->kind = $this->resolveKind($request);

        return $item;
    }

    protected function numericFields(): array
    {
        return ['total_area', 'useful_area', 'living_area', 'land_area', 'built_up_area', 'cadastral_value', 'latitude', 'longitude', 'market_index'];
    }

    protected function rules(Request $request, ?Model $item): array
    {
        $rules = [
            'kind' => ['required', Rule::enum(RealEstateKind::class)],
            'name' => ['required', 'string', 'max:255'],
            'cadastral_number' => ['nullable', 'string', 'max:60'],
            'total_area' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'useful_area' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'living_area' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'land_area' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'built_up_area' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'cadastral_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999'],
            'landmark' => ['nullable', 'string', 'max:150'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'floors_total' => ['nullable', 'integer', 'min:0', 'max:200'],
            'floor' => ['nullable', 'integer', 'min:-5', 'max:200'],
            'rooms' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'year_built' => ['nullable', 'integer', 'min:1800', 'max:'.(now()->year + 5)],
            'wall_material' => ['nullable', 'string', 'max:255'],
            'condition' => ['nullable', 'string', 'max:255'],
            'features' => ['nullable', 'array'],
            'features.*' => [Rule::enum(RealEstateFeature::class)],
            'amenities' => ['nullable', 'array', 'max:1'],
            'amenities.*' => ['string', 'max:150'],
            // The owner chosen on the page of the classic look; the other persons come in "persons".
            'owner_id' => ['sometimes', 'required', 'integer', Tenant::exists('clients')],
            'property_type' => ['nullable', 'string', 'max:150'],
            'architecture' => ['nullable', 'string', 'max:150'],
            'repair' => ['nullable', 'string', 'max:150'],
            'completion' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'light' => ['nullable', 'boolean'],
            'detached' => ['nullable', 'boolean'],
            'use_index' => ['nullable', 'boolean'],
            'market_index' => ['nullable', 'numeric', 'between:-20,20', 'required_if:use_index,1'],
            'identification' => ['nullable', 'string', 'max:5000'],
            'letter_number' => ['nullable', 'string', 'max:60'],
            // The market value, or another one named in "value_type".
            'value_kind' => ['nullable', 'in:market,other'],
            'value_type' => ['nullable', 'required_if:value_kind,other', 'string', 'max:150'],
            'function' => ['nullable', 'string', 'max:150'],
            'ownership_form' => ['nullable', 'string', 'max:150'],
            'land_rights' => ['nullable', 'string', 'max:150'],
        ];

        // The page of the classic look asks for what the valuation portal asks for.
        if ($request->exists('owner_id')) {
            $rules['approaches'] = ['required', 'array', 'min:1'];
            $rules['purpose'] = ['required', Rule::enum(ValuationPurpose::class)];
            $rules['region_id'] = ['required', 'integer', 'exists:regions,id'];
            $rules['district_id'] = ['required', 'integer', Rule::exists('districts', 'id')->where('region_id', $request->integer('region_id'))];
            $rules['address'] = ['required', 'string', 'max:255'];
            foreach ([
                'cadastral_number', 'amenities', 'property_type', 'architecture', 'function', 'repair', 'completion',
                'cadastral_value', 'landmark', 'land_area', 'built_up_area', 'useful_area', 'wall_material', 'ownership_form', 'land_rights',
            ] as $key) {
                $rules[$key] = ['required', ...array_diff($rules[$key], ['nullable'])];
            }
        }

        return $rules;
    }

    protected function validated(Request $request, ?Model $item): array
    {
        $this->prepare($request);
        // The page of the classic look has no status: a new valuation starts as new.
        if (! $request->exists('status')) {
            $request->merge(['status' => $item?->status?->value ?? ValuationStatus::New->value]);
        }

        return parent::validated($request, $item);
    }

    protected function persist(Model $item, array $data, Request $request): Model
    {
        // Added on the page of the classic look (it has no date field): valued as of today.
        if (! $item->exists && $request->exists('owner_id') && blank($data['valuation_date'] ?? null)) {
            $data['valuation_date'] = now()->toDateString();
        }
        $report = [...self::REPORT_FIELDS, ...self::REPORT_EXTRA];
        $item = parent::persist($item, Arr::except($data, [...$report, 'owner_id']), $request);
        $this->keepReportFields($item, Arr::only($data, $report), $request);

        return $item;
    }

    /** Lists sent with an empty first value (so that "none chosen" is told from "not on the form") lose it. */
    private function prepare(Request $request): void
    {
        foreach (['approaches', 'features', 'amenities'] as $list) {
            if ($request->exists($list)) {
                $request->merge([$list => array_values(array_filter((array) $request->input($list), 'filled'))]);
            }
        }
    }

    /** Saves what is typed on the object page; what the page does not send stays as it is. */
    public function main(Request $request, string $id): RedirectResponse
    {
        $item = $this->find($id);
        Gate::authorize('edit-valuation', $item);
        $this->resolveKind($request, $item);
        $this->normalizeNumbers($request);
        $this->prepare($request);
        $this->prepareOwner($request);
        $rules = Arr::only([...$this->baseRules($request, $item), ...$this->rules($request, $item)], self::MAIN_FIELDS);
        $data = $request->validate($rules, [], $this->attributeNames());

        $report = [...self::REPORT_FIELDS, ...self::REPORT_EXTRA];
        DB::transaction(function () use ($item, $data, $report, $request) {
            $item->fill(Arr::except($data, [...$report, 'owner_id', 'persons', 'approaches']));
            if ($request->exists('approaches')) {
                // A value is kept only for the approaches that are applied.
                $item->approaches = $data['approaches'] ?? [];
                foreach (ValuationApproach::cases() as $approach) {
                    if (! in_array($approach->value, $item->approaches, true)) {
                        $item->{$approach->column()} = null;
                    }
                }
            }
            $item->save();
            if ($request->exists('persons')) {
                $item->syncPersons($data['persons'] ?? []);
            }
            $this->keepReportFields($item, Arr::only($data, $report), $request);
        });

        return redirect()->route('admin.real-estates.show', $item->id)->with('status', __('Changes saved.'));
    }

    /**
     * Report fields of the form go to the report data; an emptied one takes
     * its default again. Fields the form did not have are left as they are.
     */
    private function keepReportFields(RealEstate $item, array $data, Request $request): void
    {
        $present = array_filter(self::REPORT_FIELDS, fn ($key) => $request->exists($key) || ($key === 'value_type' && $request->exists('value_kind')));
        if ($present === [] && ! $request->exists('use_index')) {
            return;
        }
        if (($data['value_kind'] ?? null) === 'market') {
            $data['value_type'] = RealEstateReport::MARKET_VALUE;
        }
        // Ticks are kept only when ticked.
        foreach (['light', 'detached'] as $flag) {
            $data[$flag] = ! empty($data[$flag]) ?: null;
        }

        $report = (array) $item->report;
        foreach ($present as $key) {
            if (filled($data[$key] ?? null)) {
                $report[$key] = $data[$key];
            } else {
                unset($report[$key]);
            }
        }
        // The price index of the comparative approach, while its box is ticked.
        if ($request->exists('use_index')) {
            if (! empty($data['use_index']) && is_numeric($data['market_index'] ?? null)) {
                $report['market_index'] = (float) $data['market_index'];
            } else {
                unset($report['market_index']);
            }
        }
        $item->report = $report;
        $item->save();
    }

    /** In the classic look the object page is its form, as on the valuation portal. */
    public function usesPortal(): bool
    {
        return AdminTheme::classic();
    }

    protected function portalView(): string
    {
        return 'admin.real-estates.portal';
    }

    protected function attributeNames(): array
    {
        return [
            ...parent::attributeNames(), 'name' => __('Object name'), 'total_area' => __('Total area'),
            'owner_id' => __('Owner of the property'), 'approaches' => __('Approach methods'), 'purpose' => __('Purpose of valuation'),
            'cadastral_number' => __('Cadastral number'), 'features' => __('Utilities'), 'amenities' => __('Special amenities'), 'property_type' => __('Type of the property'),
            'architecture' => __('Architectural style (facade)'), 'function' => __('Purpose of use'), 'repair' => __('Repair condition'),
            'completion' => __('Construction completed (%)'), 'ownership_form' => __('Form of ownership'),
            'land_rights' => __('Ownership right to the land plot'),
        ];
    }

    /**
     * "Akt" of the valuation portal, a window over the list and the object page:
     * the valued amount, the approaches, the documents to look at and the data
     * of the object taken at the inspection. Opened as a page when asked for
     * without the window.
     */
    public function act(Request $request, string $id): View
    {
        $item = $this->find($id);
        $item->load(['clients.attachments', 'attachments']);
        // As on the portal: the owner's passport first, then the documents of the object, its photos last.
        $owner = $item->clients->firstWhere('pivot.role', PersonRole::Owner->value);
        $documents = $item->clients->sortByDesc(fn ($client) => $client->is($owner))
            ->flatMap(fn ($client) => $client->attachments->filter->isImage())
            ->concat($item->attachments->filter->isImage()->sortBy(fn ($a) => $a->documentKind() === DocumentKind::Photo ? 1 : 0))
            ->values();
        $data = [
            'item' => $item,
            'rv' => RealEstateReport::values($item),
            'documents' => $documents,
            'owner' => $owner,
            'canEdit' => Gate::allows('edit-valuation', $item),
            'residential' => $item->kind === RealEstateKind::Residential,
        ];

        return view($request->ajax() ? 'admin.real-estates._act' : 'admin.real-estates.act', $data);
    }

    /**
     * The questionnaire window ("So'rovnoma ma'lumotlari" of the portal): what
     * the customer states about the object, question by question. A page when
     * not opened as a window.
     */
    public function survey(Request $request, string $id): View
    {
        $item = $this->find($id);
        $data = [
            'item' => $item,
            'answers' => [...Survey::defaults($item), ...(array) (((array) $item->report)['survey'] ?? [])],
            'canEdit' => Gate::allows('edit-valuation', $item),
        ];

        return view($request->ajax() ? 'admin.real-estates._survey' : 'admin.real-estates.survey', $data);
    }

    /** Saves the questionnaire window and goes back to where it was opened from. */
    public function saveSurvey(Request $request, string $id): RedirectResponse
    {
        $item = $this->find($id);
        Gate::authorize('edit-valuation', $item);
        $validator = Validator::make($request->all(), [
            'survey' => ['nullable', 'array'],
            'survey.*' => ['nullable', 'string', 'max:1000'],
        ], [], ['survey.*' => __('Answer')]);
        if ($validator->fails()) {
            return redirect()->route('admin.real-estates.survey', $item->id)->withErrors($validator)->withInput();
        }

        $report = (array) $item->report;
        $survey = Survey::answers($item, (array) $request->input('survey', []));
        if ($survey) {
            $report['survey'] = $survey;
        } else {
            unset($report['survey']);
        }
        $item->report = $report;
        $item->save();

        $back = (string) $request->input('_back');
        $back = str_starts_with($back, url('/').'/') && ! str_contains($back, '/survey') ? $back : route('admin.real-estates.show', $item->id);

        return redirect()->to($back)->with('status', __('Changes saved.'));
    }

    /** Saves the act window: the approaches, the owner and the data of the object. */
    public function saveAct(Request $request, string $id): RedirectResponse
    {
        $item = $this->find($id);
        Gate::authorize('edit-valuation', $item);
        $this->prepare($request);
        $required = fn (array $rules) => ['required', ...$rules];
        $validator = Validator::make($request->all(), [
            'approaches' => ['required', 'array', 'min:1'],
            'approaches.*' => [Rule::enum(ValuationApproach::class)],
            'owner_id' => ['nullable', 'integer', Tenant::exists('clients')],
            'function' => $required(['string', 'max:150']),
            'architecture' => $required(['string', 'max:150']),
            'amenities' => ['required', 'array', 'min:1', 'max:1'],
            'amenities.*' => ['string', 'max:150'],
            'repair' => $required(['string', 'max:150']),
            'completion' => $required(['numeric', 'min:0', 'max:100']),
            'ownership_form' => $required(['string', 'max:150']),
            'land_rights' => $required(['string', 'max:150']),
        ], [], $this->attributeNames());
        // A mistake is shown on the act as a page (the window would be gone after the reload).
        if ($validator->fails()) {
            return redirect()->route('admin.real-estates.act', $item->id)->withErrors($validator)->withInput();
        }
        $data = $validator->validated();

        DB::transaction(function () use ($item, $data) {
            // A value is kept only for the approaches that are applied.
            $item->approaches = array_values(array_unique($data['approaches']));
            foreach (ValuationApproach::cases() as $approach) {
                if (! in_array($approach->value, $item->approaches, true)) {
                    $item->{$approach->column()} = null;
                }
            }
            $report = (array) $item->report;
            foreach (self::ACT_FIELDS as $key) {
                $report[$key] = $data[$key];
            }
            $item->report = $report;
            $item->save();

            // Another owner chosen: the others linked to the object stay with their roles.
            $owner = PersonRole::Owner->value;
            if (filled($data['owner_id'] ?? null) && $item->clients->firstWhere('pivot.role', $owner)?->id !== (int) $data['owner_id']) {
                $others = $item->clients->filter(fn ($c) => $c->pivot->role !== $owner && $c->id !== (int) $data['owner_id'])
                    ->map(fn ($c) => ['client_id' => $c->id, 'role' => $c->pivot->role])->values()->all();
                $item->syncPersons([['client_id' => (int) $data['owner_id'], 'role' => $owner], ...$others]);
            }
        });

        // Back to where the act was opened from: the list or the object page.
        $back = (string) $request->input('_back');
        $back = str_starts_with($back, url('/').'/') && ! str_contains($back, '/act') ? $back : route('admin.real-estates.show', $item->id);

        return redirect()->to($back)->with('status', __('The act has been saved.'));
    }

    protected function formData(Model $item): array
    {
        return [
            'features' => RealEstateFeature::options(),
            // Landmarks typed before, the most used first, in the alphabet of the language.
            'landmarks' => RealEstate::query()->whereNotNull('landmark')->toBase()
                ->select('landmark', DB::raw('count(*) as uses'))->groupBy('landmark')->orderByDesc('uses')->limit(300)->pluck('landmark')
                ->map(fn ($name) => Transliterator::forLocale($name))->unique(fn ($name) => mb_strtolower($name))->values(),
        ];
    }

    public function columns(): array
    {
        return [
            __('Area') => fn (RealEstate $i) => $i->total_area
                ? new HtmlString('<span class="nowrap">'.e(Format::number($i->total_area, 1).' '.__('m²')).'</span>')
                : '—',
            __('Special amenities') => function (RealEstate $i) {
                $features = $i->featureList();
                if ($features === []) {
                    return '—';
                }
                $chips = collect($features)->take(3)->map(fn ($f) => '<span class="chip">'.e($f->label()).'</span>')->implode('');
                $more = count($features) > 3 ? '<span class="chip chip-brand">+'.(count($features) - 3).'</span>' : '';

                return new HtmlString('<div class="chips">'.$chips.$more.'</div>');
            },
        ];
    }

    public function facts(Model $item): array
    {
        /** @var RealEstate $item */
        $m2 = ' '.__('m²');

        return [
            __('Object type') => $item->kind->label(),
            __('Object name') => $item->name,
            __('Cadastral number') => $item->cadastral_number,
            __('Total area') => $item->total_area ? Format::number($item->total_area, 2).$m2 : null,
            __('Useful area') => $item->useful_area ? Format::number($item->useful_area, 2).$m2 : null,
            __('Living area') => $item->living_area ? Format::number($item->living_area, 2).$m2 : null,
            __('Land area') => $item->land_area ? Format::number($item->land_area, 2).$m2 : null,
            __('Built-up area') => $item->built_up_area ? Format::number($item->built_up_area, 2).$m2 : null,
            __('Rooms') => $item->rooms,
            __('Floor') => $item->floor ? $item->floor.($item->floors_total ? ' / '.$item->floors_total : '') : ($item->floors_total ? __('Floors: :n', ['n' => $item->floors_total]) : null),
            __('Year built') => $item->year_built,
            __('Wall material') => $item->wall_material,
            __('Technical condition') => $item->condition,
            __('Price per m²') => $item->pricePerSquareMeter() ? Format::money($item->pricePerSquareMeter()) : null,
            __('Cadastral or book value') => $item->cadastral_value !== null ? Format::money($item->cadastral_value) : null,
            __('Landmark (massif, neighbourhood)') => $item->landmark,
        ];
    }

    protected function exportColumns(): array
    {
        return [
            __('Object type') => fn ($i) => $i->kind->label(),
            __('Object name') => fn ($i) => $i->name,
            __('Cadastral number') => fn ($i) => $i->cadastral_number,
            __('Total area') => fn ($i) => $i->total_area,
            __('Useful area') => fn ($i) => $i->useful_area,
            __('Land area') => fn ($i) => $i->land_area,
            __('Built-up area') => fn ($i) => $i->built_up_area,
            __('Cadastral or book value') => fn ($i) => $i->cadastral_value,
            __('Landmark (massif, neighbourhood)') => fn ($i) => $i->landmark,
            __('Latitude') => fn ($i) => $i->latitude,
            __('Longitude') => fn ($i) => $i->longitude,
            __('Year built') => fn ($i) => $i->year_built,
            __('Special amenities') => fn ($i) => collect($i->featureList())->map->label()->implode(', '),
        ];
    }
}
