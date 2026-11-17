<?php

namespace Tests\Unit;

use App\Models\Analog;
use App\Support\ComparativeCalculator;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ComparativeCalculatorTest extends TestCase
{
    private function analog(int $id, float $priceUzs, float $area, array $attributes = []): Analog
    {
        $analog = new Analog(['status' => Analog::READY, 'price' => $priceUzs, 'currency' => 'UZS', 'price_uzs' => $priceUzs, 'area' => $area, 'included' => true, ...$attributes]);
        $analog->id = $id;

        return $analog;
    }

    /** An offer in dollars at the rate of its day. */
    private function dollars(int $id, float $usd, float $rate, float $area, array $attributes = []): Analog
    {
        return $this->analog($id, round($usd * $rate, 2), $area, ['price' => $usd, 'currency' => 'USD', 'usd_rate' => $rate, ...$attributes]);
    }

    /**
     * The house in Chirchiq the valuation portal valued (the screenshot the user sent): three houses, every
     * intermediate price and the result agree with the portal's to the sum.
     */
    public function test_the_portals_valuation_is_worked_out_the_same(): void
    {
        $subject = ['size_coef' => 0.31, 'land_coef' => 0.23, 'land_price' => 1_484_315.86, 'land_area' => 554, 'condition' => 'Qoniqarli',
            'completion' => 100, 'communications' => ['water', 'gas', 'electricity', 'sewerage']];
        $analogs = collect([
            $this->dollars(1, 55_000, 12_128.46, 152.33, ['condition' => 'Qoniqarli', 'communications' => ['water', 'gas', 'electricity', 'heating'],
                'adjustments' => ['bargain' => -5, 'location' => 0, 'land_area' => 515, 'land_coef' => 0.23, 'completion' => 100]]),
            $this->dollars(2, 53_289.14, 12_057.32, 108.41, ['condition' => 'Yaxshi', 'communications' => ['water', 'gas', 'electricity', 'sewerage', 'heating'],
                'adjustments' => ['bargain' => 0, 'location' => 100 / 12, 'land_area' => 400, 'land_coef' => 0.26, 'completion' => 100]]),
            $this->dollars(3, 80_000, 11_897.90, 174.2, ['condition' => 'Yaxshi', 'communications' => ['water', 'gas', 'electricity', 'sewerage'],
                'adjustments' => ['bargain' => -5, 'location' => 30, 'land_area' => 904, 'land_coef' => 0.24, 'completion' => 100]]),
        ]);

        $result = ComparativeCalculator::calculate(73.25, $analogs, ['usd_rate' => 12_023.61, 'subject' => $subject]);
        $rows = $result['rows'];

        // Price per m² of the offers: 4 379 080.29 · 5 926 798.76 · 5 464 018.37.
        $this->assertEqualsWithDelta(4_379_080.29, $rows[1]['price_m2'], 2.0);
        // The portal rounds this one its own way (642 524 254.00 / 108.41 = 5 926 798.39): half a sum off.
        $this->assertEqualsWithDelta(5_926_798.76, $rows[2]['price_m2'], 2.0);
        $this->assertEqualsWithDelta(5_464_018.37, $rows[3]['price_m2'], 2.0);
        // After the sale conditions (0.95 / 1 / 0.95) and the market conditions (rates).
        $this->assertEqualsWithDelta(4_160_126.27, $rows[1]['steps']['bargain']['value'], 0.02);
        $this->assertEqualsWithDelta(4_124_162.16, $rows[1]['steps']['market']['value'], 2.0);
        $this->assertEqualsWithDelta(5_910_228.55, $rows[2]['steps']['market']['value'], 2.0);
        $this->assertEqualsWithDelta(5_245_662.23, $rows[3]['steps']['market']['value'], 2.0);
        // Location 0 / 8.33 / 30 %, size 0.31 − 0.26, condition 1 − (30 − 15) %.
        $this->assertEqualsWithDelta(6_402_747.59, $rows[2]['steps']['location']['value'], 2.0);
        $this->assertSame(0.26, $rows[1]['data']['size_coef']);
        $this->assertEqualsWithDelta(5.0, $rows[1]['steps']['area']['percent'], 0.0001);
        $this->assertEqualsWithDelta(7_160_328.94, $rows[3]['steps']['area']['value'], 2.0);
        $this->assertEqualsWithDelta(-15.0, $rows[3]['steps']['condition']['percent'], 0.0001);
        $this->assertEqualsWithDelta(6_086_279.60, $rows[3]['steps']['condition']['value'], 2.0);
        // Land: 1 484 315.86 × (0.23 − 0.24) = −14 843.16; the house with more land than the object loses its price per m².
        $this->assertEqualsWithDelta(-14_843.16, $rows[3]['land']['sum'], 2.0);
        $this->assertEqualsWithDelta(1_499_159.02, $rows[3]['land']['after'], 2.0);
        $this->assertEqualsWithDelta(1_528_845.34, $rows[2]['land']['after'], 2.0);
        $this->assertTrue($rows[3]['land']['taken']);
        $this->assertFalse($rows[2]['land']['taken']);
        $this->assertEqualsWithDelta(4_587_120.58, $rows[3]['steps']['land']['value'], 2.0);
        // Utilities: five against four, −1 %.
        $this->assertEqualsWithDelta(5_657_307.71, $rows[2]['adjusted_m2'], 2.0);
        $this->assertEqualsWithDelta(4_330_370.27, $rows[1]['adjusted_m2'], 2.0);
        $this->assertEqualsWithDelta(4_587_120.58, $rows[3]['adjusted_m2'], 2.0);

        // Adjustments 4 / 6 / 6, weights 42.857 / 28.571 / 28.571 %.
        $this->assertSame([1 => 4, 2 => 6, 3 => 6], array_map(fn ($r) => $r['count'], $rows));
        $this->assertEqualsWithDelta(3 / 7, $rows[1]['weight'], 0.000001);
        $this->assertEqualsWithDelta(2 / 7, $rows[2]['weight'], 0.000001);
        $this->assertEqualsWithDelta(1_855_872.97, $rows[1]['weighted'], 2.0);

        // 4 782 852.48 × 73.25 + 1 484 315.86 × 554 = 1 172 654 930.89.
        $this->assertEqualsWithDelta(4_782_852.48, $result['price_m2'], 2.0);
        $this->assertEqualsWithDelta(350_343_944.45, $result['building'], 10);
        $this->assertEqualsWithDelta(822_310_986.44, $result['land_value'], 0.01);
        $this->assertEqualsWithDelta(1_172_654_930.89, $result['value'], 10);
    }

    public function test_an_analogue_starts_with_the_bargain_of_an_asking_price(): void
    {
        $suggested = ComparativeCalculator::suggest([], $this->analog(1, 1, 60));

        $this->assertSame(-5.0, $suggested['bargain']);
        $this->assertSame(ComparativeCalculator::MANUAL, array_keys($suggested));
        $this->assertSame(0.0, $suggested['location']);
    }

    public function test_size_coefficients_condition_and_completion_are_worked_out(): void
    {
        $this->assertSame(0.31, ComparativeCalculator::sizeCoefficient(73.25));
        $this->assertSame(0.26, ComparativeCalculator::sizeCoefficient(152.33));
        $this->assertNull(ComparativeCalculator::sizeCoefficient(null));
        $this->assertSame(30.0, ComparativeCalculator::wear('Qoniqarli'));
        $this->assertSame(15.0, ComparativeCalculator::wear('Yaxshi'));
        $this->assertSame(40.0, ComparativeCalculator::wear('Qoniqarsiz'));
        $this->assertSame(5.0, ComparativeCalculator::wear('Juda yaxshi'));

        $analogs = collect([
            $this->analog(1, 600_000_000, 60, ['condition' => 'Qoniqarsiz', 'adjustments' => ['bargain' => 0, 'size_coef' => 0.31, 'completion' => 80]]),
        ]);
        $result = ComparativeCalculator::calculate(60, $analogs, ['subject' => ['condition' => 'Yaxshi', 'completion' => 100]]);
        $steps = $result['rows'][1]['steps'];
        $this->assertSame(0.0, $steps['area']['percent'], 'The same size coefficient.');
        $this->assertSame(25.0, $steps['condition']['percent'], 'A worse analogue is raised: 1 − (15 − 40) %.');
        $this->assertSame(20.0, $steps['completion']['percent']);
        $this->assertSame(0.0, $result['land_value'], 'No land price, no land.');
        $this->assertEqualsWithDelta(10_000_000 * 1.25 * 1.2 * 60, $result['value'], 0.01);
    }

    public function test_weights_can_follow_the_size_of_adjustments(): void
    {
        $analogs = collect([
            $this->analog(1, 600_000_000, 60, ['adjustments' => ['bargain' => -5, 'location' => 2]]),
            $this->analog(2, 660_000_000, 60, ['adjustments' => ['bargain' => -5, 'location' => 10, 'other' => -10]]),
        ]);

        $result = ComparativeCalculator::calculate(50, $analogs, ['weighting' => 'size']);

        $w1 = 1 / 1.07;
        $w2 = 1 / 1.25;
        $this->assertEqualsWithDelta($w1 / ($w1 + $w2), $result['rows'][1]['weight'], 0.0001);
        $this->assertEqualsWithDelta((9_690_000 * $w1 + 10_345_500 * $w2) / ($w1 + $w2), $result['price_m2'], 0.01);
    }

    public function test_market_conditions_follow_the_exchange_rates_of_the_offer_date(): void
    {
        // 50 000 USD at 12 000 on the offer date; 12 600 on the valuation date → +5 %, a typed value is ignored.
        $analog = $this->dollars(1, 50_000, 12_000, 60, ['adjustments' => ['market' => 30, 'bargain' => 0]]);
        $result = ComparativeCalculator::calculate(60, collect([$analog]), ['usd_rate' => 12_600]);
        $this->assertSame(5.0, $result['rows'][1]['steps']['market']['percent']);
        $this->assertSame(10_500_000.0, $result['rows'][1]['adjusted_m2']);
        $this->assertSame(2, $result['rows'][1]['count'], 'The market conditions and the location, which always counts.');

        // A price in sum with the rate of its day follows the rates too, as on the portal.
        $sums = $this->analog(2, 600_000_000, 60, ['usd_rate' => 12_000, 'adjustments' => ['bargain' => 0]]);
        $this->assertSame(5.0, ComparativeCalculator::calculate(60, collect([$sums]), ['usd_rate' => 12_600])['rows'][2]['steps']['market']['percent']);
        $this->assertSame(0.0, ComparativeCalculator::calculate(60, collect([$analog]))['rows'][1]['steps']['market']['percent'], 'Without today\'s rate nothing changes.');
    }

    public function test_market_conditions_can_follow_a_monthly_price_index(): void
    {
        // Offered on 1 July, valued on 1 October (92 days = 3.02 months), prices rise 1 % a month.
        $sums = $this->analog(1, 600_000_000, 60, ['listed_at' => '2026-07-01']);
        $dollars = $this->dollars(2, 50_000, 12_000, 60, ['listed_at' => '2026-07-01']);
        $unknown = $this->analog(3, 600_000_000, 60);
        $options = ['usd_rate' => 12_600, 'index' => 1.0, 'valuation_date' => '2026-10-01'];

        $result = ComparativeCalculator::calculate(60, collect([$sums, $dollars, $unknown]), $options);

        $this->assertSame(3.02, ComparativeCalculator::months(Carbon::parse('2026-07-01'), Carbon::parse('2026-10-01')));
        $this->assertEqualsWithDelta((1.01 ** 3.02 - 1) * 100, $result['rows'][1]['steps']['market']['percent'], 0.0001);
        $this->assertEqualsWithDelta((1.05 * 1.01 ** 3.02 - 1) * 100, $result['rows'][2]['steps']['market']['percent'], 0.0001, 'Dollars: the rates and the index.');
        $this->assertSame(0.0, $result['rows'][3]['steps']['market']['percent'], 'Without the offer date nothing follows the prices.');
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
        $result = ComparativeCalculator::calculate(60, collect([$this->analog(1, 600_000_000, 60, ['included' => false])]));

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

        // Counts 4, 3 and 4 give 30 / 40 / 30 %.
        $adjust = fn (int $n) => ['bargain' => 0, ...array_slice(['rights' => 1, 'financing' => 1, 'material' => 1, 'other' => 1], 0, $n - 1)];
        $analogs = collect([
            $this->analog(1, 600_000_000, 60, ['adjustments' => $adjust(4)]),
            $this->analog(2, 660_000_000, 60, ['adjustments' => $adjust(3)]),
            $this->analog(3, 540_000_000, 60, ['adjustments' => $adjust(4)]),
        ]);
        $result = ComparativeCalculator::calculate(61.52, $analogs, ['weighting' => 'count10']);
        $this->assertSame([0.3, 0.4, 0.3], array_values(array_column($result['rows'], 'weight')));
        $this->assertSame('count10', $result['weighting']);
    }

    public function test_each_communication_the_analogue_lacks_adds_one_percent(): void
    {
        $object = ['electricity', 'gas', 'water', 'sewerage', 'heating', 'hot_water'];
        $this->assertSame(2.0, ComparativeCalculator::amenities($object, ['electricity', 'gas', 'water', 'sewerage']));
        $this->assertSame(0.0, ComparativeCalculator::amenities($object, null), 'Unknown communications: no adjustment.');
        $this->assertSame(-1.0, ComparativeCalculator::amenities(['gas'], ['gas', 'water']));

        $analog = $this->analog(1, 600_000_000, 60, ['communications' => ['gas', 'water', 'electricity', 'sewerage'], 'adjustments' => ['bargain' => 0]]);
        $result = ComparativeCalculator::calculate(60, collect([$analog]), ['subject' => ['communications' => $object]]);
        $this->assertSame(2.0, $result['rows'][1]['steps']['amenities']['percent']);
    }
}
