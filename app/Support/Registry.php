<?php

namespace App\Support;

use App\Models\Asset;
use App\Models\DamageCase;
use App\Models\OtherAsset;
use App\Models\RealEstate;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;

/**
 * The list of valuation object types known to the application.
 */
class Registry
{
    /** Morph alias => model class. */
    public const VALUATION_MODELS = [
        'real_estate' => RealEstate::class,
        'asset' => Asset::class,
        'vehicle' => Vehicle::class,
        'other_asset' => OtherAsset::class,
        'damage_case' => DamageCase::class,
    ];

    /** @return array<string, class-string<Model>> */
    public static function valuationModels(): array
    {
        return self::VALUATION_MODELS;
    }

    public static function findByVerifyCode(string $code): ?Model
    {
        if ($code === '') {
            return null;
        }

        foreach (self::VALUATION_MODELS as $class) {
            if ($model = $class::where('verify_code', $code)->first()) {
                return $model;
            }
        }

        return null;
    }
}
