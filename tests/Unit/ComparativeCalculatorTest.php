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

    public function test_suggested_adjustments_follow_bargaining_floor_and_area(): void
    {
        $subject = ['area' => 60.0, 'floor' => 4, 'floors_total' => 9];

        $firstFloor = ComparativeCalculator::suggest($subject, $this->analog(1, 1, 60, ['floor' => 1, 'floors_total' => 9]));
        $this->assertSame(-5.0, $firstFloor['bargain']);
        $this->assertSame(5.0, $firstFloor['floor'], 'A first-floor analogue is cheaper, so its price is raised.');
        $this->assertSame(0.0, $firstFloor['area']);

        $lastFloor = ComparativeCalculator::suggest($subject, $this->analog(2, 1, 60, ['floor' => 9, 'floors_total' => 9]));
        $this->assertSame(3.0, $lastFloor['floor']);

        $bigger = ComparativeCalculator::suggest($subject, $this->analog(3, 1, 120));
        $this->assertGreaterThan(0, $bigger['area'], 'A bigger flat is cheaper per m², so its price is raised.');
        $this->assertLessThanOrEqual(10, $bigger['area']);

        $smaller = ComparativeCalculator::suggest($subject, $this->analog(4, 1, 30));
        $this->assertLessThan(0, $smaller['area']);
    }

    public function test_value_is_a_weighted_average_of_adjusted_prices(): void
    {
        $analogs = collect([
            $this->analog(1, 600_000_000, 60, ['adjustments' => ['bargain' => -5]]),        // 10 000 000/m² → 9 500 000
            $this->analog(2, 660_000_000, 60, ['adjustments' => ['bargain' => -5, 'floor' => 10]]), // 11 000 000 → 11 550 000
            $this->analog(3, 900_000_000, 60, ['included' => false]),
            $this->analog(4, 500_000_000, 0),                                                 // no area
        ]);

        $result = ComparativeCalculator::calculate(50, $analogs);

        $this->assertSame(2, $result['count']);
        $this->assertSame(9_500_000.0, $result['rows'][1]['adjusted_m2']);
        $this->assertSame(11_550_000.0, $result['rows'][2]['adjusted_m2']);
        // Fewer adjustments → larger weight.
        $this->assertGreaterThan($result['rows'][2]['weight'], $result['rows'][1]['weight']);
        $this->assertEqualsWithDelta(1.0, $result['rows'][1]['weight'] + $result['rows'][2]['weight'], 0.0001);

        $w1 = 1 / 1.05;
        $w2 = 1 / 1.15;
        $expected = (9_500_000 * $w1 + 11_550_000 * $w2) / ($w1 + $w2);
        $this->assertEqualsWithDelta($expected, $result['price_m2'], 0.01);
        $this->assertSame(round($expected * 50, -3), $result['value']);
        $this->assertGreaterThan(0, $result['variation']);
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
}
