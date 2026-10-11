<?php

namespace App\Http\Controllers\Admin;

use App\Models\CarModel;
use App\Support\Format;
use App\Support\NumberInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * "Автолар рўйхати" — vehicle models used by spare part prices and norms.
 */
class CarModelController extends CatalogController
{
    protected function model(): string
    {
        return CarModel::class;
    }

    protected function slug(): string
    {
        return 'car-models';
    }

    public function nav(): string
    {
        return 'damage.models';
    }

    public function title(): string
    {
        return __('List of cars');
    }

    public function singular(): string
    {
        return __('Car model');
    }

    public function icon(): string
    {
        return 'car-front';
    }

    public function description(): ?string
    {
        return __('Car models with their own standard hour rate. Spare part prices and repair norms can be tied to a model.');
    }

    public function fields(): array
    {
        return [
            'brand' => [__('Brand'), 'text', null, 4, true],
            'model' => [__('Model'), 'text', null, 4, true],
            'years' => [__('Years of production'), 'text', null, 4],
            'category' => [__('Class'), 'text', null, 4],
            'body_type' => [__('Body type'), 'text', null, 4],
            'labor_rate' => [__('Standard hour rate'), 'money', null, 4],
            'notes' => [__('Notes'), 'textarea', null, 12],
        ];
    }

    public function columns(): array
    {
        return [
            __('Car model') => fn (CarModel $m) => $m->title,
            __('Class') => fn (CarModel $m) => $m->category ?? '—',
            __('Body type') => fn (CarModel $m) => $m->body_type ?? '—',
            __('Years of production') => fn (CarModel $m) => $m->years ?? '—',
            __('Standard hour rate') => fn (CarModel $m) => $m->labor_rate ? Format::money($m->labor_rate) : '—',
            __('Spare parts') => fn (CarModel $m) => $m->spare_parts_count,
        ];
    }

    protected function query(Request $request): Builder
    {
        return parent::query($request)->withCount('spareParts')->orderBy('model');
    }

    protected function rules(): array
    {
        return [
            'brand' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'years' => ['nullable', 'string', 'max:40'],
            'category' => ['nullable', 'string', 'max:40'],
            'body_type' => ['nullable', 'string', 'max:40'],
            'labor_rate' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function searchable(): array
    {
        return ['brand', 'model', 'category'];
    }

    protected function csvColumns(): array
    {
        return [
            __('Brand') => fn ($m) => $m->brand,
            __('Model') => fn ($m) => $m->model,
            __('Class') => fn ($m) => $m->category,
            __('Body type') => fn ($m) => $m->body_type,
            __('Years of production') => fn ($m) => $m->years,
            __('Standard hour rate') => fn ($m) => $m->labor_rate,
        ];
    }

    protected function importRow(array $row): void
    {
        [$brand, $model] = [$row[0] ?? '', $row[1] ?? ''];
        if ($brand === '' || $model === '') {
            throw new \InvalidArgumentException('Brand and model are required');
        }

        CarModel::updateOrCreate(['brand' => $brand, 'model' => $model], [
            'category' => ($row[2] ?? '') ?: null,
            'body_type' => ($row[3] ?? '') ?: null,
            'years' => ($row[4] ?? '') ?: null,
            'labor_rate' => NumberInput::float($row[5] ?? null),
        ]);
    }

    protected function lookupItem(Model $item): array
    {
        return ['id' => $item->id, 'name' => $item->title, 'rate' => (float) $item->labor_rate];
    }
}
