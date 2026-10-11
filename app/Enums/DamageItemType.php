<?php

namespace App\Enums;

/** Line types in a damage calculation. */
enum DamageItemType: string
{
    use HasOptions;

    case Part = 'part';
    case SmallPart = 'small_part';
    case Labor = 'labor';
    case Paint = 'paint';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Part => __('Spare parts'),
            self::SmallPart => __('Small parts'),
            self::Labor => __('Repair work'),
            self::Paint => __('Paint materials'),
            self::Other => __('Other expenses'),
        };
    }

    /** Whether vehicle wear reduces the cost of this line. */
    public function wearApplies(): bool
    {
        return $this === self::Part;
    }
}
