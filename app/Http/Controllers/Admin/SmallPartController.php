<?php

namespace App\Http\Controllers\Admin;

use App\Models\SmallPart;
use App\Support\Format;
use App\Support\NumberInput;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * "Кичик эҳтиёт қисмлар" — fasteners, clips, seals and consumables.
 */
class SmallPartController extends CatalogController
{
    protected function model(): string
    {
        return SmallPart::class;
    }

    protected function slug(): string
    {
        return 'small-parts';
    }

    public function nav(): string
    {
        return 'damage.small';
    }

    public function title(): string
    {
        return __('Small spare parts');
    }

    public function singular(): string
    {
        return __('Small part');
    }

    public function icon(): string
    {
        return 'hash';
    }

    public function description(): ?string
    {
        return __('Clips, bolts, seals and other consumables needed for repair.');
    }

    public function fields(): array
    {
        return [
            'name' => [__('Name'), 'text', null, 6, true],
            'price' => [__('Price'), 'money', null, 2, true],
            'unit' => [__('Unit'), 'select', AssetController::units(), 2, true],
            'price_date' => [__('Price date'), 'date', null, 2],
            'notes' => [__('Notes'), 'textarea', null, 12],
        ];
    }

    public function columns(): array
    {
        return [
            __('Name') => fn (SmallPart $p) => $p->name,
            __('Unit') => fn (SmallPart $p) => AssetController::units()[$p->unit] ?? $p->unit,
            __('Price') => fn (SmallPart $p) => Format::money($p->price),
            __('Price date') => fn (SmallPart $p) => Format::date($p->price_date),
        ];
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'unit' => ['required', Rule::in(array_keys(AssetController::units()))],
            'price_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function searchable(): array
    {
        return ['name'];
    }

    protected function csvColumns(): array
    {
        return [
            __('Name') => fn ($p) => $p->name,
            __('Unit') => fn ($p) => $p->unit,
            __('Price') => fn ($p) => $p->price,
        ];
    }

    protected function importRow(array $row): void
    {
        $name = $row[0] ?? '';
        $price = NumberInput::float($row[2] ?? null);
        if ($name === '' || $price === null) {
            throw new \InvalidArgumentException('Name and price are required');
        }

        $values = [
            'unit' => array_key_exists($row[1] ?? '', AssetController::units()) ? $row[1] : 'pcs',
            'price' => $price,
            'price_date' => now()->toDateString(),
        ];
        $this->checkRow(['name' => $name, ...$values]);

        SmallPart::updateOrCreate(['name' => $name], $values);
    }

    protected function lookupItem(Model $item): array
    {
        return [
            'kind' => 'small_part',
            'name' => $item->name,
            'sub' => AssetController::units()[$item->unit] ?? $item->unit,
            'price' => (float) $item->price,
        ];
    }
}
