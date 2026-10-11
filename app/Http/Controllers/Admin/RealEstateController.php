<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RealEstateFeature;
use App\Enums\RealEstateKind;
use App\Models\RealEstate;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;

/**
 * "Янги кўчмас мулк" — non-residential (нотурар жой) and residential
 * (турар жой) property. The kind is taken from ?kind= on list and create
 * pages and from the record itself afterwards.
 */
class RealEstateController extends ValuationController
{
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
            ? __('List of residential property')
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
        return ['total_area', 'useful_area', 'living_area', 'land_area'];
    }

    protected function rules(Request $request, ?Model $item): array
    {
        return [
            'kind' => ['required', Rule::enum(RealEstateKind::class)],
            'name' => ['required', 'string', 'max:255'],
            'cadastral_number' => ['nullable', 'string', 'max:60'],
            'total_area' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'useful_area' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'living_area' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'land_area' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'floors_total' => ['nullable', 'integer', 'min:0', 'max:200'],
            'floor' => ['nullable', 'integer', 'min:-5', 'max:200'],
            'rooms' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'year_built' => ['nullable', 'integer', 'min:1800', 'max:'.(now()->year + 5)],
            'wall_material' => ['nullable', 'string', 'max:255'],
            'condition' => ['nullable', 'string', 'max:255'],
            'features' => ['nullable', 'array'],
            'features.*' => [Rule::enum(RealEstateFeature::class)],
        ];
    }

    protected function attributeNames(): array
    {
        return [...parent::attributeNames(), 'name' => __('Object name'), 'total_area' => __('Total area')];
    }

    protected function formData(Model $item): array
    {
        return ['features' => RealEstateFeature::options()];
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
            __('Rooms') => $item->rooms,
            __('Floor') => $item->floor ? $item->floor.($item->floors_total ? ' / '.$item->floors_total : '') : ($item->floors_total ? __('Floors: :n', ['n' => $item->floors_total]) : null),
            __('Year built') => $item->year_built,
            __('Wall material') => $item->wall_material,
            __('Technical condition') => $item->condition,
            __('Price per m²') => $item->pricePerSquareMeter() ? Format::money($item->pricePerSquareMeter()) : null,
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
            __('Year built') => fn ($i) => $i->year_built,
            __('Special amenities') => fn ($i) => collect($i->featureList())->map->label()->implode(', '),
        ];
    }
}
