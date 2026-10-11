<?php

namespace App\Enums;

enum ValuationStatus: string
{
    use HasOptions;

    case New = 'new';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => __('New'),
            self::InProgress => __('In progress'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    /** CSS modifier used by the status badge. */
    public function tone(): string
    {
        return match ($this) {
            self::New => 'info',
            self::InProgress => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'muted',
        };
    }
}
