<?php

namespace App\Support;

use App\Models\Analog;
use Illuminate\Support\Collection;

/**
 * Comparative approach (national valuation standard No. 10, § 23–24):
 * the price per m² of every sale offer is brought to the object being valued
 * by adjustments applied one after another, in the order of the elements of
 * comparison, and the adjusted prices are averaged with weights.
 */
final class ComparativeCalculator
{
    /**
     * Adjustments the appraiser can set, in percent, in the order they are
     * applied. "market" (market conditions) of an offer priced in dollars is
     * worked out from the exchange rates on the offer and valuation dates.
     */
    public const ADJUSTMENTS = ['rights', 'financing', 'bargain', 'market', 'location', 'area', 'floor', 'material', 'condition', 'amenities', 'other'];

    /** Weighting of the adjusted prices: the fewer (or the smaller) adjustments, the larger the weight. */
    public const WEIGHTING = ['count', 'size'];

    /** Physical wear in percent by repair state, used to compare the condition (KMK 2.01.16-97). */
    private const WEAR = [
        'excellent' => 5.0, 'good' => 15.0, 'satisfactory' => 30.0, 'poor' => 40.0,
    ];

    /**
     * Adjustments suggested for an analogue, in percent.
     *
     * @param  array{area?: float|null, floor?: int|null, floors_total?: int|null, condition?: string|null}  $subject
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

        // A worn analogue is raised to the condition of the object: (100 − wear of the object) / (100 − wear of the analogue).
        $condition = 0.0;
        $subjectWear = self::wear($subject['condition'] ?? null);
        $analogWear = self::wear($analog->condition);
        if ($subjectWear !== null && $analogWear !== null) {
            $max = (float) $config['max_condition_adjustment'];
            $condition = max(-$max, min($max, round(((100 - $subjectWear) / (100 - $analogWear) - 1) * 100, 1)));
        }

        return [
            ...array_fill_keys(self::ADJUSTMENTS, 0.0),
            'bargain' => (float) $config['bargain'],
            'area' => $area,
            'floor' => round($floor, 1),
            'condition' => $condition,
        ];
    }

    /** Wear in percent for a repair state written in Uzbek, Russian or English; null if unknown. */
    public static function wear(?string $condition): ?float
    {
        $text = mb_strtolower(str_replace(['‘', '’', 'ʻ', 'ʼ', '`'], "'", (string) $condition));
        if (trim($text) === '') {
            return null;
        }

        $patterns = [
            'poor' => "/ta'mir\\s*talab|ремонт\\s*треб|требует\\s*ремонт|needs\\s*repair|qoniqarsiz|plox|плох|без\\s*ремонт|ta'mirsiz|qora\\s*suvoq|черн/u",
            'excellent' => "/a'lo|отличн|excellent|evro|euro|евро|dizayn|дизайн|yangi\\s*ta'mir|свеж/u",
            'satisfactory' => "/qoniqarli|o'rtacha|удовлетвор|средн|satisf|average|kosmetik|космет/u",
            'good' => "/yaxshi|хорош|good|ta'mirlangan|с\\s*ремонт/u",
        ];
        foreach ($patterns as $level => $pattern) {
            if (preg_match($pattern, $text)) {
                return self::WEAR[$level];
            }
        }

        return null;
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
     * The adjustment applied for an element, in percent. Market conditions of
     * an offer in dollars: rate on the valuation date / rate on the offer date.
     */
    public static function adjustment(Analog $analog, string $key, ?float $usdRate = null): float
    {
        if ($key === 'market' && $analog->currency === 'USD') {
            return $usdRate > 0 && (float) $analog->usd_rate > 0
                ? round(($usdRate / (float) $analog->usd_rate - 1) * 100, 2)
                : 0.0;
        }

        return $analog->adjustment($key);
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
     * @param  array{usd_rate?: float|null, weighting?: string|null}  $options  usd_rate — dollar rate on the valuation date
     * @return array{rows: array<int, array{price_m2: float, steps: array<string, array{percent: float, value: float}>, adjusted_m2: float, total: float, count: int, gross: float, weight: float}>, count: int, price_m2: ?float, value: ?float, min: ?float, max: ?float, variation: ?float, weighting: string}
     */
    public static function calculate(?float $subjectArea, Collection $analogs, array $options = []): array
    {
        $weighting = in_array($options['weighting'] ?? null, self::WEIGHTING, true) ? $options['weighting'] : 'count';
        $usdRate = isset($options['usd_rate']) ? (float) $options['usd_rate'] : null;

        $rows = [];
        foreach ($analogs as $analog) {
            if (! $analog->included || ! $analog->isUsable()) {
                continue;
            }
            $priceM2 = $analog->pricePerSquareMeter();
            $value = $priceM2;
            $steps = [];
            $count = 0;
            $gross = 0.0;
            foreach (self::ADJUSTMENTS as $key) {
                $percent = self::adjustment($analog, $key, $usdRate);
                $value *= 1 + $percent / 100;
                $steps[$key] = ['percent' => $percent, 'value' => round($value, 2)];
                $gross += abs($percent);
                // Bargaining is the same for every offer, so it does not make one less comparable.
                if ($key !== 'bargain' && abs($percent) >= 0.005) {
                    $count++;
                }
            }
            $rows[$analog->id] = [
                'price_m2' => $priceM2,
                'steps' => $steps,
                'adjusted_m2' => round($value, 2),
                'total' => round(($value / $priceM2 - 1) * 100, 2),
                'count' => $count,
                'gross' => round($gross, 2),
                'raw_weight' => $weighting === 'count' ? 1 / max(1, $count) : 1 / (1 + $gross / 100),
            ];
        }

        if ($rows === []) {
            return ['rows' => [], 'count' => 0, 'price_m2' => null, 'value' => null, 'min' => null, 'max' => null, 'variation' => null, 'weighting' => $weighting];
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
            'weighting' => $weighting,
        ];
    }
}
