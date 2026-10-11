<?php

namespace App\Enums;

enum RealEstateKind: string
{
    use HasOptions;

    case NonResidential = 'nonresidential';
    case Residential = 'residential';

    public function label(): string
    {
        return match ($this) {
            self::NonResidential => __('Non-residential'),
            self::Residential => __('Residential'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::NonResidential => 'building',
            self::Residential => 'house',
        };
    }
}
