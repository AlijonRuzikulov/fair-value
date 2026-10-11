<?php

namespace App\Enums;

/** Operations listed in vehicle repair time norms. */
enum RepairOperation: string
{
    use HasOptions;

    case Replace = 'replace';
    case Repair = 'repair';
    case Paint = 'paint';
    case RemoveInstall = 'remove_install';
    case Diagnostics = 'diagnostics';

    public function label(): string
    {
        return match ($this) {
            self::Replace => __('Replacement'),
            self::Repair => __('Repair'),
            self::Paint => __('Painting'),
            self::RemoveInstall => __('Removal / installation'),
            self::Diagnostics => __('Diagnostics and adjustment'),
        };
    }
}
