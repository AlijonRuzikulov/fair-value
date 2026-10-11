<?php

namespace App\Models;

use App\Enums\AssetCategory;
use App\Models\Concerns\ValuationObject;
use App\Support\Format;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * "Мол-мулклар" — equipment, machinery, furniture, goods.
 */
class Asset extends Model
{
    use HasFactory, ValuationObject;

    protected $fillable = [
        'name', 'category', 'inventory_number', 'manufacturer', 'model', 'serial_number',
        'year_made', 'quantity', 'unit', 'condition', ...self::VALUATION_FIELDS,
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
        ];
    }

    public static function searchable(): array
    {
        return ['name', 'inventory_number', 'serial_number', 'manufacturer', 'model'];
    }

    public function getTitleAttribute(): string
    {
        return (string) $this->name;
    }

    public function typeLabel(): string
    {
        return __('Movable property');
    }

    public function categoryLabel(): ?string
    {
        return $this->category ? AssetCategory::labelFor($this->category) : null;
    }

    public function routePrefix(): string
    {
        return 'admin.assets';
    }

    public function publicFacts(): array
    {
        return [
            __('Object type') => $this->typeLabel(),
            __('Object') => $this->name,
            __('Category') => $this->categoryLabel(),
            __('Quantity') => Format::number($this->quantity, fmod((float) $this->quantity, 1.0) ? 2 : 0).' '.__($this->unit),
        ];
    }
}
