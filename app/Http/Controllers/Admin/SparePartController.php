<?php

namespace App\Http\Controllers\Admin;

use App\Models\SparePart;
use App\Support\Format;
use App\Support\NumberInput;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * "Авто эҳтиёт қисмлар" — spare part price list.
 */
class SparePartController extends CatalogController
{
    protected function model(): string
    {
        return SparePart::class;
    }

    protected function slug(): string
    {
        return 'spare-parts';
    }

    public function hasCarModel(): bool
    {
        return true;
    }

    public function nav(): string
    {
        return 'damage.parts';
    }

    public function title(): string
    {
        return __('Car spare parts');
    }

    public function singular(): string
    {
        return __('Spare part');
    }

    public function icon(): string
    {
        return 'package';
    }

    public function description(): ?string
    {
        return __('Spare part prices used in damage calculations. Parts without a model apply to all cars.');
    }

    public function fields(): array
    {
        return [
            'name' => [__('Name'), 'text', null, 6, true],
            'part_number' => [__('Part number'), 'text', null, 3],
            'car_model_id' => [__('Car model'), 'select', $this->carModelOptions(), 3],
            'price' => [__('Price'), 'money', null, 3, true],
            'unit' => [__('Unit'), 'select', AssetController::units(), 3, true],
            'price_date' => [__('Price date'), 'date', null, 3],
            'notes' => [__('Notes'), 'textarea', null, 12],
        ];
    }

    public function columns(): array
    {
        return [
            __('Name') => fn (SparePart $p) => $p->name,
            __('Part number') => fn (SparePart $p) => $p->part_number ?? '—',
            __('Car model') => fn (SparePart $p) => $p->carModel?->title ?? __('All models'),
            __('Price') => fn (SparePart $p) => Format::money($p->price),
            __('Price date') => fn (SparePart $p) => Format::date($p->price_date),
        ];
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'part_number' => ['nullable', 'string', 'max:60'],
            'car_model_id' => ['nullable', 'integer', 'exists:car_models,id'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'unit' => ['required', Rule::in(array_keys(AssetController::units()))],
            'price_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function searchable(): array
    {
        return ['name', 'part_number'];
    }

    protected function csvColumns(): array
    {
        return [
            __('Name') => fn ($p) => $p->name,
            __('Part number') => fn ($p) => $p->part_number,
            __('Unit') => fn ($p) => $p->unit,
            __('Price') => fn ($p) => $p->price,
            __('Car model') => fn ($p) => $p->carModel?->title,
        ];
    }

    protected function importRow(array $row): void
    {
        $name = $row[0] ?? '';
        $price = NumberInput::float($row[3] ?? null);
        if ($name === '' || $price === null) {
            throw new \InvalidArgumentException('Name and price are required');
        }
        $carModelId = $this->carModelId($row[4] ?? null);
        $partNumber = ($row[1] ?? '') ?: null;

        $values = [
            'name' => $name,
            'unit' => array_key_exists($row[2] ?? '', AssetController::units()) ? $row[2] : 'pcs',
            'price' => $price,
            'price_date' => now()->toDateString(),
        ];
        $this->checkRow(['part_number' => $partNumber, ...$values]);

        SparePart::updateOrCreate(
            $partNumber ? ['part_number' => $partNumber, 'car_model_id' => $carModelId] : ['name' => $name, 'car_model_id' => $carModelId],
            $values
        );
    }

    protected function lookupItem(Model $item): array
    {
        return [
            'kind' => 'part',
            'name' => $item->name,
            'sub' => collect([$item->part_number, $item->carModel?->title ?? __('All models')])->filter()->implode(' · '),
            'price' => (float) $item->price,
        ];
    }
}
