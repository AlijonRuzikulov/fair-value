<?php

namespace App\Models;

use App\Enums\RealEstateFeature;
use App\Enums\RealEstateKind;
use App\Models\Concerns\ValuationObject;
use App\Support\Format;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * "Янги кўчмас мулк" — residential (турар жой) and non-residential
 * (нотурар жой) real estate.
 */
class RealEstate extends Model
{
    use HasFactory, ValuationObject;

    protected $fillable = [
        'kind', 'name', 'cadastral_number', 'total_area', 'useful_area', 'living_area',
        'land_area', 'floors_total', 'floor', 'rooms', 'year_built', 'wall_material',
        'condition', 'features', 'report', ...self::VALUATION_FIELDS,
    ];

    protected function casts(): array
    {
        return [
            'kind' => RealEstateKind::class,
            'features' => 'array',
            'report' => 'array',
            'total_area' => 'decimal:2',
            'useful_area' => 'decimal:2',
            'living_area' => 'decimal:2',
            'land_area' => 'decimal:2',
        ];
    }

    public static function searchable(): array
    {
        return ['name', 'cadastral_number'];
    }

    public function getTitleAttribute(): string
    {
        return (string) $this->name;
    }

    public function typeLabel(): string
    {
        return $this->kind?->label() ?? __('Real estate');
    }

    public function routePrefix(): string
    {
        return 'admin.real-estates';
    }

    /** @return list<RealEstateFeature> */
    public function featureList(): array
    {
        return array_values(array_filter(array_map(
            fn ($v) => RealEstateFeature::tryFrom((string) $v),
            (array) $this->features
        )));
    }

    public function pricePerSquareMeter(): ?float
    {
        return $this->market_value && $this->total_area > 0
            ? round((float) $this->market_value / (float) $this->total_area, 2)
            : null;
    }

    public function publicFacts(): array
    {
        return [
            __('Object type') => $this->typeLabel(),
            __('Object') => $this->name,
            __('Cadastral number') => $this->cadastral_number,
            __('Location') => collect([$this->region?->name, $this->district?->name])->filter()->implode(', '),
            __('Total area') => $this->total_area ? Format::number($this->total_area, 2).' '.__('m²') : null,
        ];
    }
}
