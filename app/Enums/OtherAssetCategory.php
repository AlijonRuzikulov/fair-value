<?php

namespace App\Enums;

/** "Бошқа мол-мулк" — everything that is not real estate, vehicles or equipment. */
enum OtherAssetCategory: string
{
    use HasOptions;

    case Business = 'business';
    case Shares = 'shares';
    case Intangible = 'intangible';
    case LandRights = 'land_rights';
    case Unfinished = 'unfinished_construction';
    case Livestock = 'livestock';
    case Jewelry = 'jewelry';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Business => __('Business (enterprise)'),
            self::Shares => __('Shares and stakes'),
            self::Intangible => __('Intangible assets'),
            self::LandRights => __('Land use rights'),
            self::Unfinished => __('Unfinished construction'),
            self::Livestock => __('Livestock and plantations'),
            self::Jewelry => __('Jewelry and art'),
            self::Other => __('Other'),
        };
    }
}
