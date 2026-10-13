<?php

namespace App\Models;

use App\Enums\DamageItemType;
use App\Models\Concerns\ValuationObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "Етказилган зарар" — the cost of restoring a damaged vehicle.
 *
 * Total = parts − wear + small parts + labour + paint + other + loss of
 * commodity value. The total is stored in market_value so that the case
 * behaves like every other valuation object (lists, verification).
 */
class DamageCase extends Model
{
    use HasFactory, ValuationObject;

    protected $fillable = [
        'car_model_id', 'vehicle_name', 'plate_number', 'vin', 'year_made', 'color', 'mileage',
        'incident_date', 'incident_place', 'incident_description', 'wear_percent',
        'labor_rate', 'commodity_loss', ...self::VALUATION_FIELDS,
    ];

    protected function casts(): array
    {
        return [
            'incident_date' => 'date',
            'wear_percent' => 'decimal:2',
            'labor_rate' => 'decimal:2',
            'commodity_loss' => 'decimal:2',
            'parts_total' => 'decimal:2',
            'wear_total' => 'decimal:2',
            'small_parts_total' => 'decimal:2',
            'labor_total' => 'decimal:2',
            'paint_total' => 'decimal:2',
            'other_total' => 'decimal:2',
        ];
    }

    public function carModel(): BelongsTo
    {
        return $this->belongsTo(CarModel::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DamageCaseItem::class)->orderBy('sort')->orderBy('id');
    }

    public static function searchable(): array
    {
        return ['vehicle_name', 'plate_number', 'vin', 'incident_place'];
    }

    public function getTitleAttribute(): string
    {
        return trim($this->vehicle_name.($this->plate_number ? ' · '.$this->plate_number : ''));
    }

    public function typeLabel(): string
    {
        return __('Damage assessment');
    }

    public function routePrefix(): string
    {
        return 'admin.damage-cases';
    }

    /**
     * Replaces calculation lines and recomputes all totals.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function syncItems(array $lines): void
    {
        $this->items()->delete();

        foreach (array_values($lines) as $i => $line) {
            $type = DamageItemType::tryFrom($line['type'] ?? '');
            if (! $type || blank($line['name'] ?? null)) {
                continue;
            }
            $quantity = (float) ($line['quantity'] ?? 1) ?: 1;
            $hours = $type === DamageItemType::Labor ? (float) ($line['hours'] ?? 0) : null;
            $price = (float) ($line['unit_price'] ?? 0);

            $this->items()->create([
                'type' => $type->value,
                'name' => $line['name'],
                'operation' => $line['operation'] ?? null,
                'quantity' => $quantity,
                'hours' => $hours,
                'unit_price' => $price,
                'amount' => round($type === DamageItemType::Labor ? $hours * $price * $quantity : $quantity * $price, 2),
                'sort' => $i,
            ]);
        }

        $this->recalculate();
    }

    public function recalculate(): void
    {
        $sums = $this->items()->get()->groupBy(fn ($item) => $item->type->value)->map->sum('amount');

        $parts = (float) ($sums[DamageItemType::Part->value] ?? 0);
        $wear = round($parts * (float) $this->wear_percent / 100, 2);

        $this->forceFill([
            'parts_total' => $parts,
            'wear_total' => $wear,
            'small_parts_total' => (float) ($sums[DamageItemType::SmallPart->value] ?? 0),
            'labor_total' => (float) ($sums[DamageItemType::Labor->value] ?? 0),
            'paint_total' => (float) ($sums[DamageItemType::Paint->value] ?? 0),
            'other_total' => (float) ($sums[DamageItemType::Other->value] ?? 0),
        ]);

        $this->market_value = round(
            $parts - $wear
            + (float) $this->small_parts_total
            + (float) $this->labor_total
            + (float) $this->paint_total
            + (float) $this->other_total
            + (float) $this->commodity_loss,
            2
        );

        $this->saveQuietly();
    }

    public function publicFacts(): array
    {
        return [
            __('Object type') => $this->typeLabel(),
            __('Vehicle') => $this->vehicle_name,
            __('State number') => Vehicle::maskPlate($this->plate_number),
            __('Incident date') => $this->incident_date?->format('d.m.Y'),
        ];
    }
}
