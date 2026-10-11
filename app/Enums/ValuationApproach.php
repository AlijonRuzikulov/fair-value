<?php

namespace App\Enums;

enum ValuationApproach: string
{
    use HasOptions;

    case Cost = 'cost';
    case Comparative = 'comparative';
    case Income = 'income';

    public function label(): string
    {
        return match ($this) {
            self::Cost => __('Cost approach'),
            self::Comparative => __('Comparative approach'),
            self::Income => __('Income approach'),
        };
    }

    public function short(): string
    {
        return match ($this) {
            self::Cost => __('Cost'),
            self::Comparative => __('Comparative'),
            self::Income => __('Income'),
        };
    }

    /** Column that stores the value obtained with this approach. */
    public function column(): string
    {
        return 'value_'.$this->value;
    }
}
