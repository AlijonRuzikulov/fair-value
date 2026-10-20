<?php

namespace Tests\Unit;

use App\Models\Analog;
use App\Support\ComparativeCalculator;
use Tests\TestCase;

class ComparativeCalculatorTest extends TestCase
{
    private function analog(int $id, float $priceUzs, float $area, array $attributes = []): Analog
    {
        $analog = new Analog(['status' => Analog::READY, 'price' => $priceUzs, 'currency' => 'UZS', 'price_uzs' => $priceUzs, 'area' => $area, 'included' => true, ...$attributes]);
        $analog->id = $id;

        return $analog;
    }

    public function test_suggested_adjustments_follow_bargaining_floor_area_and_condition(): void
    {
        $subject = ['area' => 60.0, 'floor' => 4, 'floors_total' => 9, 'condition' => 'Yaxshi'];

        $firstFloor = ComparativeCalculator::suggest($subject, $this->analog(1, 1, 60, ['floor' => 1, 'floors_total' => 9]));
        $this->assertSame(-5.0, $firstFloor['bargain']);
        $this->assertSame(5.0, $firstFloor['floor'], 'A first-floor analogue is cheaper, so its price is raised.');
        $this->assertSame(0.0, $firstFloor['area']);
        $this->assertSame(0.0, $firstFloor['condition'], 'The condition of the analogue is unknown.');
        $this->assertSame(ComparativeCalculator::ADJUSTMENTS, array_keys($firstFloor));

        $lastFloor = ComparativeCalculator::suggest($subject, $this->analog(2, 1, 60, ['floor' => 9, 'floors_total' => 9]));
        $this->assertSame(3.0, $lastFloor['floor']);

        $bigger = ComparativeCalculator::suggest($subject, $this->analog(3, 1, 120));
        $this->assertGreaterThan(0, $bigger['area'], 'A bigger flat is cheaper per m², so its price is raised.');
        $this->assertLessThanOrEqual(10, $bigger['area']);

        $smaller = ComparativeCalculator::suggest($subject, $this->analog(4, 1, 30));
        $this->assertLessThan(0, $smaller['area']);

        // Wear 15 % against 30 %: (100 − 15) / (100 − 30) − 1 = +21.4 %.
        $worn = ComparativeCalculator::suggest($subject, $this->analog(5, 1, 60, ['condition' => 'Qoniqarli']));
        $this->assertSame(21.4, $worn['condition']);
        $euro = ComparativeCalculator::suggest($subject, $this->analog(6, 1, 60, ['condition' => 'Евроремонт']));
        $this->assertLessThan(0, $euro['condition']);
    }

    public function test_adjustments_are_applied_one_after_another_and_weighted_by_their_number(): void
    {
        $analogs = collect([
            $this->analog(1, 600_000_000, 60, ['adjustments' => ['bargain' => -5, 'area' => 2]]),                     // 10 000 000/m², 1 adjustment
            $this->analog(2, 660_000_000, 60, ['adjustments' => ['bargain' => -5, 'floor' => 10, 'condition' => -10]]), // 11 000 000/m², 2 adjustments
            $this->analog(3, 900_000_000, 60, ['included' => false]),
            $this->analog(4, 500_000_000, 0),                                                                          // no area
        ]);

        $result = ComparativeCalculator::calculate(50, $analogs);

        $this->assertSame('count', $result['weighting']);
        $this->assertSame(2, $result['count']);
        $this->assertSame(9_690_000.0, $result['rows'][1]['adjusted_m2']);       // 10 000 000 × 0.95 × 1.02
        $this->assertSame(10_345_500.0, $result['rows'][2]['adjusted_m2']);      // 11 000 000 × 0.95 × 1.10 × 0.90
        $this->assertSame(9_500_000.0, $result['rows'][1]['steps']['bargain']['value']);
        $this->assertSame(1, $result['rows'][1]['count'], 'Bargaining is not counted.');
        $this->assertSame(2, $result['rows'][2]['count']);
        $this->assertEqualsWithDelta(2 / 3, $result['rows'][1]['weight'], 0.0001);
        $this->assertEqualsWithDelta(1 / 3, $result['rows'][2]['weight'], 0.0001);

        $expected = 9_690_000 * 2 / 3 + 10_345_500 / 3;
        $this->assertEqualsWithDelta($expected, $result['price_m2'], 0.01);
        $this->assertSame(round($expected * 50, -3), $result['value']);
        $this->assertGreaterThan(0, $result['variation']);
    }

    public function test_weights_can_follow_the_size_of_adjustments(): void
    {
        $analogs = collect([
            $this->analog(1, 600_000_000, 60, ['adjustments' => ['bargain' => -5, 'area' => 2]]),
            $this->analog(2, 660_000_000, 60, ['adjustments' => ['bargain' => -5, 'floor' => 10, 'condition' => -10]]),
        ]);

        $result = ComparativeCalculator::calculate(50, $analogs, ['weighting' => 'size']);

        $w1 = 1 / 1.07;
        $w2 = 1 / 1.25;
        $this->assertEqualsWithDelta($w1 / ($w1 + $w2), $result['rows'][1]['weight'], 0.0001);
        $this->assertEqualsWithDelta((9_690_000 * $w1 + 10_345_500 * $w2) / ($w1 + $w2), $result['price_m2'], 0.01);
    }

    public function test_market_conditions_of_a_dollar_price_follow_the_exchange_rates(): void
    {
        // 50 000 USD at 12 000 on the offer date; 12 600 on the valuation date → +5 %.
        $analog = $this->analog(1, 600_000_000, 60, ['price' => 50_000, 'currency' => 'USD', 'usd_rate' => 12_000, 'adjustments' => ['market' => 30]]);

        $result = ComparativeCalculator::calculate(60, collect([$analog]), ['usd_rate' => 12_600]);

        $this->assertSame(5.0, $result['rows'][1]['steps']['market']['percent'], 'A typed value is ignored for dollar prices.');
        $this->assertSame(10_500_000.0, $result['rows'][1]['adjusted_m2']);
        $this->assertSame(1, $result['rows'][1]['count']);

        $withoutRate = ComparativeCalculator::calculate(60, collect([$analog]));
        $this->assertSame(0.0, $withoutRate['rows'][1]['steps']['market']['percent']);
    }

    public function test_offers_far_from_the_median_are_outliers(): void
    {
        $analogs = collect([
            $this->analog(1, 600_000_000, 60),
            $this->analog(2, 630_000_000, 60),
            $this->analog(3, 620_000_000, 60),
            $this->analog(4, 1_500_000_000, 60),
        ]);

        $this->assertSame([4], ComparativeCalculator::outliers($analogs));
        $this->assertSame([], ComparativeCalculator::outliers($analogs->take(2)), 'Too few offers to judge.');
    }

    public function test_empty_selection_gives_no_value(): void
    {
        $result = ComparativeCalculator::calculate(60, collect());

        $this->assertNull($result['value']);
        $this->assertSame(0, $result['count']);
    }

    public function test_weights_can_be_rounded_to_whole_tens_of_percent(): void
    {
        $this->assertSame([1 => 0.3, 2 => 0.4, 3 => 0.3], ComparativeCalculator::roundWeights([1 => 3 / 11, 2 => 4 / 11, 3 => 3 / 11]));
        $this->assertSame([7 => 0.5, 8 => 0.5], ComparativeCalculator::roundWeights([7 => 0.5, 8 => 0.5]));
        $five = ComparativeCalculator::roundWeights([0.05, 0.05, 0.3, 0.3, 0.3]);
        $this->assertEqualsWithDelta(1.0, array_sum($five), 0.0001);
        $this->assertGreaterThanOrEqual(0.1, min($five), 'Every analogue keeps at least one step.');
        $six = ComparativeCalculator::roundWeights(array_fill(0, 6, 1 / 6));
        $this->assertEqualsWithDelta(1.0, array_sum($six), 0.0001);
        $this->assertSame(0.0, fmod(round(min($six) * 100), 5.0), 'Steps of 5 % for more than five analogues.');

        // Counts 4, 3 and 4 give 30 / 40 / 30 %, as in the template report.
        $adjust = fn (int $n) => array_slice(['rights' => 1, 'location' => 1, 'area' => 1, 'floor' => 1, 'condition' => 1], 0, $n);
        $analogs = collect([
            $this->analog(1, 600_000_000, 60, ['adjustments' => $adjust(4)]),
            $this->analog(2, 660_000_000, 60, ['adjustments' => $adjust(3)]),
            $this->analog(3, 540_000_000, 60, ['adjustments' => $adjust(4)]),
        ]);
        $result = ComparativeCalculator::calculate(61.52, $analogs, ['weighting' => 'count10']);
        $this->assertSame([0.3, 0.4, 0.3], array_values(array_column($result['rows'], 'weight')));
        $expected = $result['rows'][1]['adjusted_m2'] * 0.3 + $result['rows'][2]['adjusted_m2'] * 0.4 + $result['rows'][3]['adjusted_m2'] * 0.3;
        $this->assertEqualsWithDelta($expected, $result['price_m2'], 0.01);
        $this->assertSame('count10', $result['weighting']);
    }

    public function test_each_communication_the_analogue_lacks_adds_one_percent(): void
    {
        $object = ['electricity', 'gas', 'water', 'sewerage', 'heating', 'hot_water'];
        $this->assertSame(2.0, ComparativeCalculator::amenities($object, ['electricity', 'gas', 'water', 'sewerage']));
        $this->assertSame(0.0, ComparativeCalculator::amenities($object, null), 'Unknown communications: no adjustment.');
        $this->assertSame(-1.0, ComparativeCalculator::amenities(['gas'], ['gas', 'water']));

        $suggested = ComparativeCalculator::suggest(['area' => 60.0, 'communications' => $object], $this->analog(1, 1, 60, ['communications' => ['gas', 'water', 'electricity', 'sewerage']]));
        $this->assertSame(2.0, $suggested['amenities']);
    }
}
