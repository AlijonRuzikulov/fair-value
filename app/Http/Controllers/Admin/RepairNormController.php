<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RepairOperation;
use App\Models\RepairNorm;
use App\Support\Format;
use App\Support\NumberInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * "Автотранспорт қисмларини таъмир муддати нормалари" — standard hours.
 */
class RepairNormController extends CatalogController
{
    protected function model(): string
    {
        return RepairNorm::class;
    }

    protected function slug(): string
    {
        return 'repair-norms';
    }

    public function hasCarModel(): bool
    {
        return true;
    }

    public function nav(): string
    {
        return 'damage.norms';
    }

    public function title(): string
    {
        return __('Repair time norms');
    }

    public function singular(): string
    {
        return __('Repair norm');
    }

    public function icon(): string
    {
        return 'timer';
    }

    public function description(): ?string
    {
        return __('Standard hours for repair operations of vehicle parts. The cost of work = norm hours × standard hour rate.');
    }

    public function fields(): array
    {
        return [
            'part_name' => [__('Part / unit'), 'text', null, 6, true],
            'operation' => [__('Operation'), 'select', RepairOperation::options(), 3, true],
            'hours' => [__('Norm hours'), 'number', null, 3, true],
            'car_model_id' => [__('Car model'), 'select', $this->carModelOptions(), 6],
            'notes' => [__('Notes'), 'textarea', null, 12],
        ];
    }

    public function columns(): array
    {
        return [
            __('Part / unit') => fn (RepairNorm $n) => $n->part_name,
            __('Operation') => fn (RepairNorm $n) => $n->operation?->label(),
            __('Norm hours') => fn (RepairNorm $n) => Format::number($n->hours, 2),
            __('Car model') => fn (RepairNorm $n) => $n->carModel?->title ?? __('All models'),
        ];
    }

    protected function query(Request $request): Builder
    {
        return parent::query($request)
            ->when($request->query('operation'), fn (Builder $q, $v) => $q->where('operation', $v))
            ->orderBy('operation');
    }

    protected function rules(): array
    {
        return [
            'part_name' => ['required', 'string', 'max:255'],
            'operation' => ['required', Rule::enum(RepairOperation::class)],
            'hours' => ['required', 'numeric', 'min:0', 'max:999'],
            'car_model_id' => ['nullable', 'integer', 'exists:car_models,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function searchable(): array
    {
        return ['part_name'];
    }

    protected function csvColumns(): array
    {
        return [
            __('Part / unit') => fn ($n) => $n->part_name,
            __('Operation') => fn ($n) => $n->operation?->value,
            __('Norm hours') => fn ($n) => $n->hours,
            __('Car model') => fn ($n) => $n->carModel?->title,
        ];
    }

    protected function importRow(array $row): void
    {
        $name = $row[0] ?? '';
        $hours = NumberInput::float($row[2] ?? null);
        $operation = RepairOperation::tryFrom($row[1] ?? '')
            ?? collect(RepairOperation::cases())->first(fn ($op) => mb_strtolower($op->label()) === mb_strtolower($row[1] ?? ''));
        if ($name === '' || $hours === null || ! $operation) {
            throw new \InvalidArgumentException('Part, operation and hours are required');
        }

        $this->checkRow(['part_name' => $name, 'operation' => $operation->value, 'hours' => $hours]);

        RepairNorm::updateOrCreate(
            ['part_name' => $name, 'operation' => $operation->value, 'car_model_id' => $this->carModelId($row[3] ?? null)],
            ['hours' => $hours]
        );
    }

    protected function lookupItem(Model $item): array
    {
        return [
            'kind' => 'labor',
            'name' => $item->part_name,
            'sub' => $item->operation?->label().' · '.Format::number($item->hours, 2).' '.__('h').' · '.($item->carModel?->title ?? __('All models')),
            'operation' => $item->operation?->value,
            'hours' => (float) $item->hours,
        ];
    }
}
