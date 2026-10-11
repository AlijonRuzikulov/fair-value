<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OtherAssetCategory;
use App\Models\OtherAsset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * "Бошқа мол-мулк" — business, shares, intangible assets and the rest.
 */
class OtherAssetController extends ValuationController
{
    protected function model(): string
    {
        return OtherAsset::class;
    }

    protected function slug(): string
    {
        return 'other-assets';
    }

    protected function nav(Request $request, ?Model $item = null): string
    {
        return 'other.list';
    }

    public function title(): string
    {
        return __('List of other property');
    }

    public function singular(): string
    {
        return __('Other property');
    }

    public function icon(): string
    {
        return 'monitor';
    }

    protected function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->query('category'), fn (Builder $q, $v) => $q->where('category', $v));
    }

    protected function rules(Request $request, ?Model $item): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', Rule::enum(OtherAssetCategory::class)],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function formData(Model $item): array
    {
        return ['categories' => OtherAssetCategory::options()];
    }

    public function columns(): array
    {
        return [__('Category') => fn (OtherAsset $i) => $i->categoryLabel() ?? '—'];
    }

    public function facts(Model $item): array
    {
        /** @var OtherAsset $item */
        return [
            __('Name') => $item->name,
            __('Category') => $item->categoryLabel(),
            __('Description') => $item->description,
        ];
    }

    protected function exportColumns(): array
    {
        return [
            __('Name') => fn ($i) => $i->name,
            __('Category') => fn ($i) => $i->categoryLabel(),
            __('Description') => fn ($i) => $i->description,
        ];
    }
}
