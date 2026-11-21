<?php

namespace App\Models;

use App\Enums\RealEstateFeature;
use App\Enums\RealEstateKind;
use App\Models\Concerns\ValuationObject;
use App\Reports\RealEstateReport;
use App\Support\Format;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'condition', 'features', 'report', 'latitude', 'longitude', 'landmark', 'cadastral_value', 'built_up_area',
        ...self::VALUATION_FIELDS,
    ];

    /**
     * The final market value follows the reconciliation of the approaches, as
     * on the valuation portal: it changes with the values of the approaches and
     * the scores. A value typed by the appraiser that differs from it is kept
     * ("final_typed" in the report) until the field is cleared or the reconciled
     * value is taken again.
     */
    protected static function booted(): void
    {
        static::saving(function (RealEstate $object) {
            $report = (array) $object->report;
            // Scores saved while an approach was not applied give it none: once applied, it starts with one by every factor.
            if ($object->isDirty('approaches') && is_array($report['scores'] ?? null)) {
                $added = array_diff((array) $object->approaches, (array) $object->getOriginal('approaches'));
                $scores = $report['scores'];
                foreach ($added as $approach) {
                    if (array_sum(array_map(fn ($row) => (float) ($row[$approach] ?? 0), $scores)) == 0) {
                        foreach (array_keys($scores) as $i) {
                            $scores[$i][$approach] = 1;
                        }
                    }
                }
                if ($scores !== $report['scores']) {
                    $report['scores'] = $scores;
                    $object->report = $report;
                }
            }
            $reconciled = RealEstateReport::reconciledValue($object);
            $value = $object->market_value !== null ? (float) $object->market_value : null;
            $manual = $object->isDirty('market_value')
                // Typed now: kept when it differs; an empty field or the reconciled value gives it back to the reconciliation.
                ? $value !== null && ($reconciled === null || abs($value - $reconciled) >= 0.005)
                : ! empty($report['final_typed']) && $value !== null;

            if ($manual !== ! empty($report['final_typed'])) {
                if ($manual) {
                    $report['final_typed'] = true;
                } else {
                    unset($report['final_typed']);
                }
                $object->report = $report;
            }
            if (! $manual) {
                $object->market_value = $reconciled;
            }
        });
    }

    /**
     * The area the comparative approach multiplies the price per m² by: the
     * total area, or the useful (then living) area while the total is empty
     * (only the useful area is required on the add page).
     */
    public function comparedArea(): ?float
    {
        foreach ([$this->total_area, $this->useful_area, $this->living_area] as $area) {
            if ((float) $area > 0) {
                return (float) $area;
            }
        }

        return null;
    }

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
            'built_up_area' => 'decimal:2',
            'cadastral_value' => 'decimal:2',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * "10:01:05:01:02:0123", kept without spaces and without a colon or a slash left at the end.
     * Nineteen digits are a land plot numbered by the newer rule: "10:04:02:01:03:00451/0012".
     */
    protected function cadastralNumber(): Attribute
    {
        return Attribute::make(set: function (?string $value) {
            $value = $value === null ? '' : rtrim(preg_replace('/\s+/u', '', $value), ':/');
            $digits = preg_replace('/\D/', '', $value);
            if (strlen($digits) === 19 && preg_match('/^[\d:\/.\-]+$/', $value)) {
                $value = implode(':', str_split(substr($digits, 0, 10), 2)).':'.substr($digits, 10, 5).'/'.substr($digits, 15);
            }

            return $value !== '' ? $value : null;
        });
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

    /** A flat in a multi-storey building: its "Foydalanish maqsadi" is "Ko'p qavatli turar joy". */
    public function isFlat(): bool
    {
        $function = (string) (RealEstateReport::values($this)['function'] ?? '');

        return $this->kind === RealEstateKind::Residential
            && mb_strtolower(str_replace(['‘', '’', 'ʻ', 'ʼ'], "'", $function)) === "ko'p qavatli turar joy";
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
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
