<?php

namespace App\Models;

use App\Enums\FuelType;
use App\Models\Concerns\ValuationObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * "Автотранспорт"
 */
class Vehicle extends Model
{
    use HasFactory, ValuationObject;

    protected $fillable = [
        'brand', 'model', 'year_made', 'body_type', 'color', 'vin', 'engine_number',
        'chassis_number', 'plate_number', 'tech_passport', 'mileage', 'engine_volume',
        'fuel_type', 'transmission', 'condition', ...self::VALUATION_FIELDS,
    ];

    protected function casts(): array
    {
        return [
            'engine_volume' => 'decimal:1',
        ];
    }

    public static function searchable(): array
    {
        return ['brand', 'model', 'vin', 'plate_number', 'engine_number', 'tech_passport'];
    }

    public function getTitleAttribute(): string
    {
        return trim($this->brand.' '.$this->model).($this->year_made ? " ({$this->year_made})" : '');
    }

    public function typeLabel(): string
    {
        return __('Vehicle');
    }

    public function routePrefix(): string
    {
        return 'admin.vehicles';
    }

    public function fuelLabel(): ?string
    {
        return $this->fuel_type ? FuelType::labelFor($this->fuel_type) : null;
    }

    public function publicFacts(): array
    {
        return [
            __('Object type') => $this->typeLabel(),
            __('Object') => $this->title,
            __('State number') => self::maskPlate($this->plate_number),
            __('Body number (VIN)') => self::mask($this->vin),
        ];
    }

    /** Hides the middle of an identifier: "XWB3L32EDCA123456" → "XWB•••••••••123456". */
    public static function mask(?string $value): ?string
    {
        if (! $value || strlen($value) < 8) {
            return $value;
        }

        return substr($value, 0, 3).str_repeat('•', strlen($value) - 9).substr($value, -6);
    }

    /** Shows only the region code and the last letters: "01 A 123 BC" → "01 ••• BC". */
    public static function maskPlate(?string $value): ?string
    {
        $value = trim((string) $value);
        if (mb_strlen($value) < 5) {
            return $value === '' ? null : $value;
        }

        return mb_substr($value, 0, 2).' ••• '.mb_substr($value, -2);
    }
}
