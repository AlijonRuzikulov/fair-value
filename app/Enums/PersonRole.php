<?php

namespace App\Enums;

/** Role of a client in relation to a valuation object ("Шахслар"). */
enum PersonRole: string
{
    use HasOptions;

    case Owner = 'owner';
    case Customer = 'customer';
    case Representative = 'representative';
    case Pledgee = 'pledgee';

    public function label(): string
    {
        return match ($this) {
            self::Owner => __('Owner'),
            self::Customer => __('Customer'),
            self::Representative => __('Representative'),
            self::Pledgee => __('Pledgee (bank)'),
        };
    }
}
