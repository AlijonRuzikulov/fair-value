<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AssetCategory;
use App\Models\Asset;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * "Мол-мулклар" — equipment, machinery, furniture and goods.
 */
class AssetController extends ValuationController
{
    protected function model(): string
    {
        return Asset::class;
    }

    protected function slug(): string
    {
        return 'assets';
    }

    public function title(): string
    {
        return __('List of movable property');
    }

    public function singular(): string
    {
        return __('Movable property');
    }

    public function icon(): string
    {
        return 'boxes';
    }

    protected function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->query('category'), fn (Builder $q, $v) => $q->where('category', $v));
    }

    protected function numericFields(): array
    {
        return ['quantity'];
    }

    protected function rules(Request $request, ?Model $item): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', Rule::enum(AssetCategory::class)],
            'inventory_number' => ['nullable', 'string', 'max:60'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:80'],
            'year_made' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            'quantity' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'unit' => ['required', Rule::in(array_keys(self::units()))],
            'condition' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function newItem(Request $request): Model
    {
        $item = parent::newItem($request);
        $item->quantity = 1;
        $item->unit = 'pcs';

        return $item;
    }

    protected function formData(Model $item): array
    {
        return ['categories' => AssetCategory::options(), 'units' => self::units()];
    }

    public static function units(): array
    {
        return ['pcs' => __('pcs'), 'set' => __('set'), 'kg' => __('kg'), 't' => __('t'), 'm' => __('m'), 'm2' => __('m²'), 'l' => __('l')];
    }

    public function columns(): array
    {
        return [
            __('Category') => fn (Asset $i) => $i->categoryLabel() ?? '—',
            __('Quantity') => fn (Asset $i) => Format::number($i->quantity, fmod((float) $i->quantity, 1.0) ? 2 : 0).' '.(self::units()[$i->unit] ?? $i->unit),
        ];
    }

    public function facts(Model $item): array
    {
        /** @var Asset $item */
        return [
            __('Name') => $item->name,
            __('Category') => $item->categoryLabel(),
            __('Inventory number') => $item->inventory_number,
            __('Manufacturer') => $item->manufacturer,
            __('Model') => $item->model,
            __('Serial number') => $item->serial_number,
            __('Year of manufacture') => $item->year_made,
            __('Quantity') => Format::number($item->quantity, fmod((float) $item->quantity, 1.0) ? 2 : 0).' '.(self::units()[$item->unit] ?? $item->unit),
            __('Technical condition') => $item->condition,
        ];
    }

    protected function exportColumns(): array
    {
        return [
            __('Name') => fn ($i) => $i->name,
            __('Category') => fn ($i) => $i->categoryLabel(),
            __('Inventory number') => fn ($i) => $i->inventory_number,
            __('Manufacturer') => fn ($i) => $i->manufacturer,
            __('Model') => fn ($i) => $i->model,
            __('Serial number') => fn ($i) => $i->serial_number,
            __('Year of manufacture') => fn ($i) => $i->year_made,
            __('Quantity') => fn ($i) => $i->quantity,
        ];
    }
}
