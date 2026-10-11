<?php

namespace App\Models;

use App\Enums\OtherAssetCategory;
use App\Models\Concerns\ValuationObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * "Бошқа мол-мулк" — business, shares, intangibles, land rights, etc.
 */
class OtherAsset extends Model
{
    use HasFactory, ValuationObject;

    protected $fillable = ['name', 'category', 'description', ...self::VALUATION_FIELDS];

    public static function searchable(): array
    {
        return ['name', 'description'];
    }

    public function getTitleAttribute(): string
    {
        return (string) $this->name;
    }

    public function typeLabel(): string
    {
        return __('Other property');
    }

    public function categoryLabel(): ?string
    {
        return $this->category ? OtherAssetCategory::labelFor($this->category) : null;
    }

    public function routePrefix(): string
    {
        return 'admin.other-assets';
    }

    public function publicFacts(): array
    {
        return [
            __('Object type') => $this->typeLabel(),
            __('Object') => $this->name,
            __('Category') => $this->categoryLabel(),
        ];
    }
}
