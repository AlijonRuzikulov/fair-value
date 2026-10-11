<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DamageItemType;
use App\Enums\RepairOperation;
use App\Enums\ValuationApproach;
use App\Enums\ValuationPurpose;
use App\Models\CarModel;
use App\Models\DamageCase;
use App\Models\Setting;
use App\Support\Format;
use App\Support\NumberInput;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;

/**
 * "Етказилган зарар" — restoration cost of a damaged vehicle, calculated
 * from spare part prices, repair time norms, paint and other expenses.
 */
class DamageCaseController extends ValuationController
{
    protected function model(): string
    {
        return DamageCase::class;
    }

    protected function slug(): string
    {
        return 'damage-cases';
    }

    protected function nav(Request $request, ?Model $item = null): string
    {
        return 'damage.cases';
    }

    public function title(): string
    {
        return __('Damage assessments');
    }

    public function singular(): string
    {
        return __('Damage assessment');
    }

    public function icon(): string
    {
        return 'wrench';
    }

    public function usesApproaches(): bool
    {
        return false;
    }

    protected function newItem(Request $request): Model
    {
        $item = parent::newItem($request);
        $item->purpose = ValuationPurpose::Damage->value;
        $item->wear_percent = 0;
        $item->labor_rate = (float) Setting::get('labor_rate');
        $item->commodity_loss = 0;

        return $item;
    }

    protected function numericFields(): array
    {
        return ['wear_percent', 'labor_rate', 'commodity_loss', 'mileage'];
    }

    protected function extraRelations(): array
    {
        return ['items', 'carModel'];
    }

    protected function rules(Request $request, ?Model $item): array
    {
        // Prices inside calculation lines may be typed with separators too.
        $request->merge(['items' => collect($request->input('items', []))->map(fn ($line) => is_array($line) ? [
            ...$line,
            'quantity' => NumberInput::normalize($line['quantity'] ?? null),
            'hours' => NumberInput::normalize($line['hours'] ?? null),
            'unit_price' => NumberInput::normalize($line['unit_price'] ?? null),
        ] : $line)->all()]);

        return [
            'car_model_id' => ['nullable', 'integer', 'exists:car_models,id'],
            'vehicle_name' => ['required', 'string', 'max:255'],
            'plate_number' => ['nullable', 'string', 'max:20'],
            'vin' => ['nullable', 'string', 'max:40'],
            'year_made' => ['nullable', 'integer', 'min:1950', 'max:'.(now()->year + 1)],
            'color' => ['nullable', 'string', 'max:40'],
            'mileage' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'incident_date' => ['nullable', 'date'],
            'incident_place' => ['nullable', 'string', 'max:255'],
            'incident_description' => ['nullable', 'string', 'max:5000'],
            'wear_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'labor_rate' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'commodity_loss' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'items' => ['nullable', 'array', 'max:300'],
            'items.*.type' => ['required', Rule::enum(DamageItemType::class)],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.operation' => ['nullable', Rule::enum(RepairOperation::class)],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'items.*.hours' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ];
    }

    protected function attributeNames(): array
    {
        return [
            ...parent::attributeNames(),
            'vehicle_name' => __('Vehicle'),
            'items.*.name' => __('Name'),
            'items.*.type' => __('Type'),
        ];
    }

    protected function persist(Model $item, array $data, Request $request): Model
    {
        return DB::transaction(function () use ($item, $data, $request) {
            $lines = $data['items'] ?? [];
            unset($data['items']);
            $data['approaches'] = [ValuationApproach::Cost->value];
            $data['commodity_loss'] ??= 0;

            /** @var DamageCase $item */
            $item = parent::persist($item, $data, $request);
            $item->syncItems($lines);

            return $item;
        });
    }

    protected function formData(Model $item): array
    {
        return [
            'carModels' => CarModel::orderBy('brand')->orderBy('model')->get(),
            'itemTypes' => DamageItemType::options(),
            'operations' => RepairOperation::options(),
            'lines' => $item->exists ? $item->items()->get() : collect(),
        ];
    }

    public function columns(): array
    {
        return [
            __('State number') => fn (DamageCase $i) => $i->plate_number
                ? new HtmlString('<span class="code">'.e($i->plate_number).'</span>')
                : '—',
            __('Incident date') => fn (DamageCase $i) => Format::date($i->incident_date),
        ];
    }

    public function facts(Model $item): array
    {
        /** @var DamageCase $item */
        return [
            __('Vehicle') => $item->vehicle_name,
            __('State number') => $item->plate_number,
            __('Body number (VIN)') => $item->vin,
            __('Year of manufacture') => $item->year_made,
            __('Color') => $item->color,
            __('Mileage') => $item->mileage !== null ? Format::number($item->mileage).' '.__('km') : null,
            __('Incident date') => Format::date($item->incident_date),
            __('Incident place') => $item->incident_place,
            __('Circumstances') => $item->incident_description,
            __('Vehicle wear') => Format::number($item->wear_percent, 2).' %',
            __('Standard hour rate') => Format::money($item->labor_rate),
        ];
    }

    protected function exportColumns(): array
    {
        return [
            __('Vehicle') => fn ($i) => $i->vehicle_name,
            __('State number') => fn ($i) => $i->plate_number,
            __('Body number (VIN)') => fn ($i) => $i->vin,
            __('Incident date') => fn ($i) => Format::date($i->incident_date),
            __('Spare parts') => fn ($i) => $i->parts_total,
            __('Wear') => fn ($i) => $i->wear_total,
            __('Repair work') => fn ($i) => $i->labor_total,
            __('Paint materials') => fn ($i) => $i->paint_total,
            __('Loss of commodity value') => fn ($i) => $i->commodity_loss,
        ];
    }
}
