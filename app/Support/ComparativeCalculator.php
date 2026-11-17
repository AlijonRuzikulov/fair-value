<?php

namespace App\Support;

use App\Models\Analog;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Comparative approach worked out as on the valuation portal the appraisers
 * use: the price of every offer in dollars is brought to sum at the rate of
 * its date, its price per m² is adjusted element after element, the adjusted
 * prices are weighted by the inverse number of adjustments, and the value is
 * the weighted price per m² × the total area plus the land: its price per m²
 * × its area.
 */
final class ComparativeCalculator
{
    /**
     * The elements of comparison that change the price, in the order they are
     * applied. Of these the appraiser types the percentages of rights,
     * financing, bargain (sale conditions), location, material (walls),
     * architecture, function and other; the rest are worked out: market (the
     * dollar rate of the valuation date / that of the offer date, with a
     * monthly price index if one is set), area (the difference of the size
     * coefficients), condition (the difference of the wear by repair state),
     * land, completion and amenities (one percent per engineering communication).
     */
    public const ADJUSTMENTS = ['rights', 'financing', 'bargain', 'market', 'location', 'area', 'material', 'architecture', 'condition', 'function', 'land', 'completion', 'amenities', 'other'];

    /** Percentages typed by the appraiser (kept in the analogue's "adjustments"). */
    public const MANUAL = ['rights', 'financing', 'bargain', 'location', 'material', 'architecture', 'function', 'other'];

    /**
     * Weighting of the adjusted prices: the fewer (or the smaller) adjustments,
     * the larger the weight. "count" (by the number, as on the portal) is the
     * usual one; "count10" rounds it to whole tens of percent.
     */
    public const WEIGHTING = ['count', 'count10', 'size'];

    /** Physical wear in percent by repair state, used to compare the condition (as on the portal: good 15, satisfactory 30). */
    private const WEAR = [
        'excellent' => 5.0, 'good' => 15.0, 'satisfactory' => 30.0, 'poor' => 40.0,
    ];

    /**
     * Percentages an analogue starts with: the bargain of an asking price (an
     * offer, not a closed deal), nothing else.
     *
     * @return array<string, float>
     */
    public static function suggest(array $subject, Analog $analog): array
    {
        return [...array_fill_keys(self::MANUAL, 0.0), 'bargain' => (float) config('fairvalue.analogs.bargain')];
    }

    /**
     * The size coefficient of a building by its total area (the bigger, the
     * smaller); the adjustment for area is the difference of the coefficients
     * of the object and of the analogue.
     */
    public static function sizeCoefficient(?float $area): ?float
    {
        if (! $area || $area <= 0) {
            return null;
        }
        foreach ((array) config('fairvalue.analogs.size_coefficients') as [$upTo, $coefficient]) {
            if ($upTo === null || $area < $upTo) {
                return (float) $coefficient;
            }
        }

        return null;
    }

    /**
     * Engineering communications: each one the object has and the analogue
     * does not raises the analogue's price by a step (1 % by default).
     *
     * @param  list<string>  $subject
     * @param  list<string>|null  $analog  null when not known
     */
    public static function amenities(array $subject, ?array $analog): float
    {
        if ($analog === null || $subject === []) {
            return 0.0;
        }

        return round((count(array_unique($subject)) - count(array_unique($analog))) * (float) config('fairvalue.analogs.communication_step', 1.0), 2);
    }

    /** Wear in percent for a repair state written in Uzbek, Russian or English; null if unknown. */
    public static function wear(?string $condition): ?float
    {
        $text = mb_strtolower(str_replace(['‘', '’', 'ʻ', 'ʼ', '`'], "'", (string) $condition));
        if (trim($text) === '') {
            return null;
        }

        $patterns = [
            'poor' => "/avariya|аварий|ta'mir\\s*talab|ремонт\\s*треб|требует\\s*ремонт|needs\\s*repair|qoniqarsiz|plox|плох|без\\s*ремонт|ta'mirsiz|qora\\s*suvoq|черн|қониқарсиз/u",
            'excellent' => "/juda\\s*yaxshi|жуда\\s*яхши|a'lo|отличн|excellent|evro|euro|евро|dizayn|дизайн|yangi\\s*ta'mir|свеж|аъло/u",
            'satisfactory' => "/qoniqarli|o'rtacha|удовлетвор|средн|satisf|average|kosmetik|космет|қониқарли/u",
            'good' => "/yaxshi|хорош|good|ta'mirlangan|с\\s*ремонт|яхши/u",
        ];
        foreach ($patterns as $level => $pattern) {
            if (preg_match($pattern, $text)) {
                return self::WEAR[$level];
            }
        }

        return null;
    }

    /** Months (with their part) from the offer to the valuation date; negative for an offer made later. */
    public static function months(CarbonInterface $from, CarbonInterface $to): float
    {
        return round($from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay(), false) / 30.4375, 2);
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
     * The data of the object the worked-out adjustments compare with, with
     * what is missing filled in.
     *
     * @param  array<string, mixed>  $subject
     * @return array{area: ?float, size_coef: ?float, land_area: ?float, land_coef: ?float, land_price: float, wear: ?float, completion: ?float, communications: list<string>}
     */
    public static function subject(?float $area, array $subject): array
    {
        $number = fn ($v) => is_numeric($v) ? (float) $v : null;

        return [
            'area' => $area > 0 ? $area : null,
            'size_coef' => $number($subject['size_coef'] ?? null) ?? self::sizeCoefficient($area),
            'land_area' => $number($subject['land_area'] ?? null),
            'land_coef' => $number($subject['land_coef'] ?? null),
            'land_price' => max(0.0, (float) $number($subject['land_price'] ?? null)),
            'wear' => self::wear($subject['condition'] ?? null),
            'completion' => $number($subject['completion'] ?? null),
            'communications' => array_values((array) ($subject['communications'] ?? [])),
        ];
    }

    /**
     * The worked-out data of an analogue: its size and land coefficients, land
     * area, completion and the rate of its date (typed ones first).
     *
     * @return array{size_coef: ?float, land_area: ?float, land_coef: ?float, completion: ?float, wear: ?float, rate: ?float}
     */
    public static function analogData(Analog $analog): array
    {
        $number = fn ($v) => is_numeric($v) ? (float) $v : null;
        $typed = (array) $analog->adjustments;

        return [
            'size_coef' => $number($typed['size_coef'] ?? null) ?? self::sizeCoefficient((float) $analog->area),
            'land_area' => $number($typed['land_area'] ?? null),
            'land_coef' => $number($typed['land_coef'] ?? null),
            'completion' => $number($typed['completion'] ?? null),
            'wear' => self::wear($analog->condition),
            'rate' => (float) $analog->usd_rate > 0 ? (float) $analog->usd_rate : null,
        ];
    }

    /**
     * The adjustment of an element, in percent of the price per m² before it;
     * for land, the coefficient difference in percent (its sum is taken off
     * separately).
     */
    public static function adjustment(Analog $analog, string $key, array $subject, ?float $usdRate = null, ?float $index = null, ?CarbonInterface $date = null): float
    {
        $data = self::analogData($analog);

        return match ($key) {
            // The price of the offer's day in dollars, at today's rate (as on the portal), and the monthly price index.
            'market' => round(((($usdRate > 0 && $data['rate']) ? $usdRate / $data['rate'] : 1.0)
                * ($index !== null && $date !== null && $analog->listed_at !== null ? (1 + $index / 100) ** self::months(Carbon::parse($analog->listed_at), $date) : 1.0) - 1) * 100, 4),
            'area' => $subject['size_coef'] !== null && $data['size_coef'] !== null ? round(($subject['size_coef'] - $data['size_coef']) * 100, 4) : 0.0,
            // 1 − (wear of the object − wear of the analogue): a better analogue is brought down.
            'condition' => $subject['wear'] !== null && $data['wear'] !== null ? round(-($subject['wear'] - $data['wear']), 4) : 0.0,
            'land' => $subject['land_coef'] !== null && $data['land_coef'] !== null ? round(($subject['land_coef'] - $data['land_coef']) * 100, 4) : 0.0,
            'completion' => $subject['completion'] !== null && $data['completion'] !== null ? round($subject['completion'] - $data['completion'], 4) : 0.0,
            'amenities' => self::amenities($subject['communications'], $analog->communications),
            default => $analog->adjustment($key),
        };
    }

    /**
     * @param  Collection<int, Analog>  $analogs
     * @param  array{usd_rate?: float|null, weighting?: string|null, index?: float|null, valuation_date?: CarbonInterface|string|null, subject?: array<string, mixed>}  $options  usd_rate — dollar rate on the valuation date; index — monthly change of prices, %; subject — the data of the object
     * @return array{rows: array<int, array>, count: int, price_m2: ?float, building: ?float, land_price: float, land_area: ?float, land_value: float, value: ?float, min: ?float, max: ?float, variation: ?float, weighting: string, subject: array}
     */
    public static function calculate(?float $subjectArea, Collection $analogs, array $options = []): array
    {
        $weighting = in_array($options['weighting'] ?? null, self::WEIGHTING, true) ? $options['weighting'] : 'count';
        $usdRate = isset($options['usd_rate']) ? (float) $options['usd_rate'] : null;
        $index = isset($options['index']) && is_numeric($options['index']) ? (float) $options['index'] : null;
        $date = isset($options['valuation_date']) ? Carbon::parse($options['valuation_date']) : null;
        $subject = self::subject($subjectArea, (array) ($options['subject'] ?? []));
        $landPrice = $subject['land_price'];

        $rows = [];
        foreach ($analogs as $analog) {
            if (! $analog->included || ! $analog->isUsable()) {
                continue;
            }
            $data = self::analogData($analog);
            $priceM2 = $analog->pricePerSquareMeter();
            $value = $priceM2;
            $steps = [];
            $count = 0;
            $gross = 0.0;
            $land = ['sum' => 0.0, 'after' => $landPrice, 'taken' => false];
            foreach (self::ADJUSTMENTS as $key) {
                $percent = self::adjustment($analog, $key, $subject, $usdRate, $index, $date);
                if ($key === 'land') {
                    // The land price per m² brought to the analogue; taken off the price of an analogue with more land than the object.
                    $land['sum'] = round($landPrice * $percent / 100, 2);
                    $land['after'] = round($landPrice - $land['sum'], 2);
                    if ($landPrice > 0 && $subject['land_area'] > 0 && $data['land_area'] > $subject['land_area']) {
                        $value -= $land['after'];
                        $land['taken'] = true;
                    }
                } else {
                    $value *= 1 + $percent / 100;
                }
                $steps[$key] = ['percent' => $percent, 'value' => round($value, 2)];
                $gross += abs($percent);
                // As on the portal: every element that changes the price counts, and the location always.
                if ($key === 'location' || abs($percent) >= 0.005) {
                    $count++;
                }
            }
            $rows[$analog->id] = [
                'price_m2' => $priceM2,
                'usd' => $analog->currency === 'USD' ? (float) $analog->price : ($data['rate'] ? round((float) $analog->price_uzs / $data['rate'], 2) : null),
                'rate' => $data['rate'],
                'data' => $data,
                'land' => $land,
                'steps' => $steps,
                'adjusted_m2' => round($value, 2),
                'total' => round(($value / $priceM2 - 1) * 100, 2),
                'count' => $count,
                'gross' => round($gross, 2),
                'raw_weight' => $weighting === 'size' ? 1 / (1 + $gross / 100) : 1 / max(1, $count),
            ];
        }

        $landArea = $subject['land_area'];
        $landValue = $landPrice > 0 && $landArea > 0 ? round($landPrice * $landArea, 2) : 0.0;
        if ($rows === []) {
            return ['rows' => [], 'count' => 0, 'price_m2' => null, 'building' => null, 'land_price' => $landPrice, 'land_area' => $landArea, 'land_value' => $landValue,
                'value' => null, 'min' => null, 'max' => null, 'variation' => null, 'weighting' => $weighting, 'subject' => $subject];
        }

        $weightSum = array_sum(array_column($rows, 'raw_weight'));
        $weights = array_map(fn ($row) => $row['raw_weight'] / $weightSum, $rows);
        if ($weighting === 'count10') {
            $weights = self::roundWeights($weights);
        }
        $priceM2 = 0.0;
        foreach ($rows as $id => $row) {
            $rows[$id]['weight'] = $weights[$id];
            $rows[$id]['weighted'] = round($row['adjusted_m2'] * $weights[$id], 2);
            $priceM2 += $row['adjusted_m2'] * $weights[$id];
            unset($rows[$id]['raw_weight']);
        }

        $adjusted = array_column($rows, 'adjusted_m2');
        $mean = array_sum($adjusted) / count($adjusted);
        $variance = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $adjusted)) / count($adjusted);
        $building = $subjectArea > 0 ? round($priceM2 * $subjectArea, 2) : null;

        return [
            'rows' => $rows,
            'count' => count($rows),
            'price_m2' => round($priceM2, 2),
            'building' => $building,
            'land_price' => $landPrice,
            'land_area' => $landArea,
            'land_value' => $landValue,
            'value' => $building !== null ? round($building + $landValue, 2) : null,
            'min' => min($adjusted),
            'max' => max($adjusted),
            // Over 33 % the analogues are too different to be averaged.
            'variation' => $mean > 0 ? round(sqrt($variance) / $mean * 100, 1) : null,
            'weighting' => $weighting,
            'subject' => $subject,
        ];
    }

    /**
     * Weights rounded to whole tens of percent (fives when there are more
     * than five analogues), each at least one step, adding up to 100 %.
     *
     * @param  array<int, float>  $weights  shares that add up to 1
     * @return array<int, float>
     */
    public static function roundWeights(array $weights): array
    {
        $step = count($weights) > 5 ? 5 : 10;
        $units = intdiv(100, $step);
        if (count($weights) > $units) {
            return $weights;
        }

        $exact = array_map(fn ($w) => $w * $units, $weights);
        $whole = array_map(fn ($u) => max(1, (int) floor($u)), $exact);
        // The units left go to the largest remainders; extra units come off the largest weights.
        $remainders = array_map(fn ($u, $w) => $u - $w, $exact, $whole);
        $order = array_keys($exact);
        while (array_sum($whole) < $units) {
            $best = array_keys($remainders, max($remainders))[0];
            $whole[$order[$best]]++;
            $remainders[$best] = -INF;
        }
        while (array_sum($whole) > $units) {
            $largest = array_keys($whole, max($whole))[0];
            $whole[$largest]--;
        }

        return array_map(fn ($w) => $w * $step / 100, $whole);
    }
}
