<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FuelType;
use App\Models\Vehicle;
use App\Support\Format;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;

/**
 * "Автотранспорт"
 */
class VehicleController extends ValuationController
{
    protected function model(): string
    {
        return Vehicle::class;
    }

    protected function slug(): string
    {
        return 'vehicles';
    }

    public function title(): string
    {
        return __('List of vehicles');
    }

    public function singular(): string
    {
        return __('Vehicle');
    }

    public function icon(): string
    {
        return 'car';
    }

    protected function numericFields(): array
    {
        return ['mileage', 'engine_volume'];
    }

    protected function rules(Request $request, ?Model $item): array
    {
        return [
            'brand' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'year_made' => ['nullable', 'integer', 'min:1950', 'max:'.(now()->year + 1)],
            'body_type' => ['nullable', 'string', 'max:40'],
            'color' => ['nullable', 'string', 'max:40'],
            'vin' => ['nullable', 'string', 'max:40'],
            'engine_number' => ['nullable', 'string', 'max:40'],
            'chassis_number' => ['nullable', 'string', 'max:40'],
            'plate_number' => ['nullable', 'string', 'max:20'],
            'tech_passport' => ['nullable', 'string', 'max:30'],
            'mileage' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'engine_volume' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'fuel_type' => ['nullable', Rule::enum(FuelType::class)],
            'transmission' => ['nullable', Rule::in(array_keys(self::transmissions()))],
            'condition' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function formData(Model $item): array
    {
        return ['fuelTypes' => FuelType::options(), 'transmissions' => self::transmissions()];
    }

    public static function transmissions(): array
    {
        return ['manual' => __('Manual'), 'automatic' => __('Automatic'), 'robot' => __('Robotic'), 'cvt' => __('CVT')];
    }

    public function columns(): array
    {
        return [
            __('State number') => fn (Vehicle $i) => $i->plate_number
                ? new HtmlString('<span class="code">'.e($i->plate_number).'</span>')
                : '—',
            __('Mileage') => fn (Vehicle $i) => $i->mileage !== null ? Format::number($i->mileage).' '.__('km') : '—',
        ];
    }

    public function facts(Model $item): array
    {
        /** @var Vehicle $item */
        return [
            __('Brand') => $item->brand,
            __('Model') => $item->model,
            __('Year of manufacture') => $item->year_made,
            __('Body type') => $item->body_type,
            __('Color') => $item->color,
            __('State number') => $item->plate_number,
            __('Body number (VIN)') => $item->vin,
            __('Engine number') => $item->engine_number,
            __('Chassis number') => $item->chassis_number,
            __('Registration certificate') => $item->tech_passport,
            __('Mileage') => $item->mileage !== null ? Format::number($item->mileage).' '.__('km') : null,
            __('Engine capacity') => $item->engine_volume ? Format::number($item->engine_volume, 1).' '.__('l') : null,
            __('Fuel') => $item->fuelLabel(),
            __('Transmission') => $item->transmission ? (self::transmissions()[$item->transmission] ?? $item->transmission) : null,
            __('Technical condition') => $item->condition,
        ];
    }

    protected function exportColumns(): array
    {
        return [
            __('Brand') => fn ($i) => $i->brand,
            __('Model') => fn ($i) => $i->model,
            __('Year of manufacture') => fn ($i) => $i->year_made,
            __('State number') => fn ($i) => $i->plate_number,
            __('Body number (VIN)') => fn ($i) => $i->vin,
            __('Engine number') => fn ($i) => $i->engine_number,
            __('Mileage') => fn ($i) => $i->mileage,
        ];
    }
}
