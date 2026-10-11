<?php

namespace App\Enums;

/** "Алоҳида қулайликлар" — utilities and amenities of a building. */
enum RealEstateFeature: string
{
    use HasOptions;

    case Electricity = 'electricity';
    case Gas = 'gas';
    case Water = 'water';
    case Sewerage = 'sewerage';
    case Heating = 'heating';
    case HotWater = 'hot_water';
    case Internet = 'internet';
    case Elevator = 'elevator';
    case Parking = 'parking';
    case Security = 'security';
    case Renovated = 'renovated';
    case Furniture = 'furniture';
    case AirConditioning = 'air_conditioning';
    case Basement = 'basement';

    public function label(): string
    {
        return match ($this) {
            self::Electricity => __('Electricity'),
            self::Gas => __('Natural gas'),
            self::Water => __('Water supply'),
            self::Sewerage => __('Sewerage'),
            self::Heating => __('Heating'),
            self::HotWater => __('Hot water'),
            self::Internet => __('Telephone and internet'),
            self::Elevator => __('Elevator'),
            self::Parking => __('Parking'),
            self::Security => __('Security'),
            self::Renovated => __('Renovated'),
            self::Furniture => __('Furniture'),
            self::AirConditioning => __('Air conditioning'),
            self::Basement => __('Basement'),
        };
    }
}
