<?php

namespace App\Enums;

enum UserRole: string
{
    use HasOptions;

    case Admin = 'admin';
    case Appraiser = 'appraiser';
    case Operator = 'operator';

    public function label(): string
    {
        return match ($this) {
            self::Admin => __('Administrator'),
            self::Appraiser => __('Appraiser'),
            self::Operator => __('Operator'),
        };
    }
}
