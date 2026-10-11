<?php

namespace App\Enums;

enum LeadStatus: string
{
    use HasOptions;

    case New = 'new';
    case Contacted = 'contacted';
    case Converted = 'converted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::New => __('New'),
            self::Contacted => __('Contacted'),
            self::Converted => __('Became a client'),
            self::Rejected => __('Rejected'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Contacted => 'warning',
            self::Converted => 'success',
            self::Rejected => 'muted',
        };
    }
}
