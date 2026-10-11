<?php

namespace App\Enums;

/** Categories of movable property ("Мол-мулклар"). */
enum AssetCategory: string
{
    use HasOptions;

    case Equipment = 'equipment';
    case Machinery = 'machinery';
    case Special = 'special_vehicles';
    case Agricultural = 'agricultural';
    case Computers = 'computers';
    case Furniture = 'furniture';
    case Goods = 'goods';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Equipment => __('Equipment'),
            self::Machinery => __('Machines and production lines'),
            self::Special => __('Special machinery'),
            self::Agricultural => __('Agricultural machinery'),
            self::Computers => __('Computers and office equipment'),
            self::Furniture => __('Furniture and inventory'),
            self::Goods => __('Goods and materials'),
            self::Other => __('Other'),
        };
    }
}
