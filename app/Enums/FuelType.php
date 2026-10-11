<?php

namespace App\Enums;

enum FuelType: string
{
    use HasOptions;

    case Petrol = 'petrol';
    case Methane = 'methane';
    case Propane = 'propane';
    case Diesel = 'diesel';
    case Electric = 'electric';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Petrol => __('Petrol'),
            self::Methane => __('Petrol / methane'),
            self::Propane => __('Petrol / propane'),
            self::Diesel => __('Diesel'),
            self::Electric => __('Electric'),
            self::Hybrid => __('Hybrid'),
        };
    }
}
