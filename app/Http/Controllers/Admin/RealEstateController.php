<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PersonRole;
use App\Enums\RealEstateFeature;
use App\Enums\RealEstateKind;
use App\Enums\ValuationApproach;
use App\Enums\ValuationPurpose;
use App\Enums\ValuationStatus;
use App\Models\RealEstate;
use App\Reports\RealEstateReport;
use App\Support\AdminTheme;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
        'letter_number', 'value_type', 'ownership_form', 'land_rights',
    ];

    /** The data saved on the object page (the first step, as on the valuation portal). */
    private const MAIN_FIELDS = [
        'owner_id', 'persons', 'persons.*.client_id', 'persons.*.role', 'approaches', 'approaches.*', 'purpose',
        'name', 'cadastral_number', 'features', 'features.*', 'appraiser_id', 'service_fee', 'paid_amount',
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
            // The owner chosen on the page of the classic look; the other persons come in "persons".
            'owner_id' => ['sometimes', 'required', 'integer', 'exists:clients,id'],
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
                'cadastral_number', 'features', 'property_type', 'architecture', 'function', 'repair', 'completion',
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

    /**
     * Lists sent with an empty first value (so that "none chosen" is told
     * from "not on the form") lose it; the owner chosen on its own becomes
     * the first person.
     */
    private function prepare(Request $request): void
    {
        foreach (['approaches', 'features'] as $list) {
            if ($request->exists($list)) {
                $request->merge([$list => array_values(array_filter((array) $request->input($list), 'filled'))]);
            }
        }
        if ($request->exists('owner_id')) {
            $others = array_values(array_filter((array) $request->input('persons', []), fn ($p) => is_array($p) && filled($p['client_id'] ?? null)));
            $owner = filled($request->input('owner_id')) ? [['client_id' => $request->input('owner_id'), 'role' => PersonRole::Owner->value]] : [];
            $request->merge(['persons' => [...$owner, ...$others]]);
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

    protected function showView(): string
    {
        return AdminTheme::classic() ? 'admin.real-estates.portal' : parent::showView();
    }

    protected function formView(): string
    {
        return AdminTheme::classic() ? 'admin.real-estates.portal' : parent::formView();
    }

    /** In the classic look the object page is its form, as on the valuation portal. */
    public function edit(Request $request, string $id): View|RedirectResponse
    {
        if (AdminTheme::classic()) {
            Gate::authorize('edit-valuation', $this->find($id));

            return $this->show($request, $id);
        }

        return parent::edit($request, $id);
    }

    /** The page of the classic look has the form on it. */
    protected function showData(Model $item): array
    {
        if (! AdminTheme::classic()) {
            return [];
        }

        return [
            ...$this->sharedOptions(),
            'districts' => self::districtOptions(old('region_id', $item->region_id)),
            'personRows' => $this->personRows($item),
            ...$this->formData($item),
        ];
    }

    protected function attributeNames(): array
    {
        return [
            ...parent::attributeNames(), 'name' => __('Object name'), 'total_area' => __('Total area'),
            'owner_id' => __('Owner of the property'), 'approaches' => __('Approach methods'), 'purpose' => __('Purpose of valuation'),
            'cadastral_number' => __('Cadastral number'), 'features' => __('Special amenities'), 'property_type' => __('Type of the property'),
            'architecture' => __('Architectural style (facade)'), 'function' => __('Purpose of use'), 'repair' => __('Repair condition'),
            'completion' => __('Construction completed (%)'),
        ];
    }

    protected function formData(Model $item): array
    {
        return [
            'features' => RealEstateFeature::options(),
            // Landmarks typed before, the most used first.
            'landmarks' => RealEstate::query()->whereNotNull('landmark')->toBase()
                ->select('landmark', DB::raw('count(*) as uses'))->groupBy('landmark')->orderByDesc('uses')->limit(300)->pluck('landmark'),
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
