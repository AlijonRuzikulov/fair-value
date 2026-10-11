<?php

namespace App\Enums;

enum ClientType: string
{
    use HasOptions;

    case Individual = 'individual';
    case Legal = 'legal';

    public function label(): string
    {
        return match ($this) {
            self::Individual => __('Individual'),
            self::Legal => __('Legal entity'),
        };
    }

    public function plural(): string
    {
        return match ($this) {
            self::Individual => __('Individuals'),
            self::Legal => __('Legal entities'),
        };
    }
}
