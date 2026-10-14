<?php

namespace App\Support;

use App\Models\Analog;
use Illuminate\Support\Collection;

/**
 * Comparative approach: sale offers of similar objects are brought to the
 * object being valued with percentage adjustments, weighted (the fewer
 * adjustments an analogue needs, the more it counts) and averaged per m².
 */
final class ComparativeCalculator
{
    public const ADJUSTMENTS = ['bargain', 'area', 'floor', 'location', 'condition', 'other'];

    /**
     * Adjustments suggested for an analogue, in percent.
     *
     * @param  array{area?: float|null, floor?: int|null, floors_total?: int|null}  $subject
     * @return array<string, float>
     */
    public static function suggest(array $subject, Analog $analog): array
    {
        $config = config('fairvalue.analogs');

        $area = 0.0;
        $subjectArea = (float) ($subject['area'] ?? 0);
        if ($subjectArea > 0 && (float) $analog->area > 0) {
            // A bigger analogue is cheaper per m², so its price is raised.
            $area = ((float) $analog->area / $subjectArea) ** (float) $config['area_elasticity'] - 1;
            $max = (float) $config['max_area_adjustment'];
            $area = max(-$max, min($max, round($area * 100, 1)));
        }

        $floor = 0.0;
        if (! empty($subject['floor'])) {
            $floor = self::floorFactor($subject['floor'], $subject['floors_total'] ?? null)
                - self::floorFactor($analog->floor, $analog->floors_total);
        }

        return [
            'bargain' => (float) $config['bargain'],
            'area' => $area,
            'floor' => round($floor, 1),
            'location' => 0.0,
            'condition' => 0.0,
            'other' => 0.0,
        ];
    }

    /** First and last floors sell cheaper than the middle ones. */
    private static function floorFactor(?int $floor, ?int $floorsTotal): float
    {
        if (! $floor) {
            return 0.0;
        }
        if ($floor === 1) {
            return (float) config('fairvalue.analogs.first_floor');
        }
        if ($floorsTotal && $floorsTotal > 1 && $floor >= $floorsTotal) {
            return (float) config('fairvalue.analogs.last_floor');
        }

        return 0.0;
    }

    /**
     * Leaves out analogues whose price per m² is far from the median.
     *
     * @param  Collection<int, Analog>  $analogs
     * @return list<int> ids of the analogues left out
     */
    public static function outliers(Collection $analogs): array
    {
        $usable = $analogs->filter->isUsable();
        if ($usable->count() < 3) {
            return [];
        }

        $median = $usable->map->pricePerSquareMeter()->median();
        $limit = (float) config('fairvalue.analogs.outlier');

        return $usable
            ->filter(fn (Analog $a) => abs($a->pricePerSquareMeter() / $median - 1) > $limit)
            ->pluck('id')->values()->all();
    }

    /**
     * @param  Collection<int, Analog>  $analogs
     * @return array{rows: array<int, array{price_m2: float, adjusted_m2: float, weight: float}>, count: int, price_m2: ?float, value: ?float, min: ?float, max: ?float, variation: ?float}
     */
    public static function calculate(?float $subjectArea, Collection $analogs): array
    {
        $rows = [];
        foreach ($analogs as $analog) {
            if (! $analog->included || ! $analog->isUsable()) {
                continue;
            }
            $priceM2 = $analog->pricePerSquareMeter();
            $gross = array_sum(array_map(fn ($key) => abs($analog->adjustment($key)), self::ADJUSTMENTS));
            $rows[$analog->id] = [
                'price_m2' => $priceM2,
                'adjusted_m2' => round($priceM2 * (1 + $analog->totalAdjustment() / 100), 2),
                'raw_weight' => 1 / (1 + $gross / 100),
            ];
        }

        if ($rows === []) {
            return ['rows' => [], 'count' => 0, 'price_m2' => null, 'value' => null, 'min' => null, 'max' => null, 'variation' => null];
        }

        $weightSum = array_sum(array_column($rows, 'raw_weight'));
        $priceM2 = 0.0;
        foreach ($rows as $id => $row) {
            $rows[$id]['weight'] = round($row['raw_weight'] / $weightSum, 4);
            $priceM2 += $row['adjusted_m2'] * $row['raw_weight'] / $weightSum;
            unset($rows[$id]['raw_weight']);
        }

        $adjusted = array_column($rows, 'adjusted_m2');
        $mean = array_sum($adjusted) / count($adjusted);
        $variance = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $adjusted)) / count($adjusted);

        return [
            'rows' => $rows,
            'count' => count($rows),
            'price_m2' => round($priceM2, 2),
            'value' => $subjectArea > 0 ? round($priceM2 * $subjectArea, -3) : null,
            'min' => min($adjusted),
            'max' => max($adjusted),
            // Over 33 % the analogues are too different to be averaged.
            'variation' => $mean > 0 ? round(sqrt($variance) / $mean * 100, 1) : null,
        ];
    }
}
