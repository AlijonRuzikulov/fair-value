<?php

namespace Tests\Feature\Report;

use App\Enums\ValuationStatus;
use App\Models\RealEstate;
use App\Models\User;
use App\Support\CostApproach;
use App\Support\IncomeApproach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The cost approach (buildings, wear, land) and the income approach
 * (direct capitalisation) worked out for an object and printed.
 */
class ApproachesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private RealEstate $house;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->house = RealEstate::factory()->nonResidential()->create([
            'name' => 'Savdo binosi', 'total_area' => 100, 'year_built' => 2011, 'valuation_date' => '2026-10-01',
            'approaches' => ['cost', 'income'], 'status' => ValuationStatus::InProgress, 'report' => null,
            'value_cost' => null, 'value_income' => null,
        ]);
    }

    public function test_the_cost_approach_takes_off_wear_and_adds_the_land(): void
    {
        $result = CostApproach::calculate([
            'buildings' => [
                ['name' => 'Asosiy bino', 'quantity' => 100, 'rate' => 6_500_000, 'year_built' => 2011],
                ['name' => 'Ayvon', 'quantity' => 20, 'rate' => 1_000_000, 'year_built' => 2006, 'light' => true, 'functional' => 10],
                ['name' => 'Bo‘sh qator', 'quantity' => null, 'rate' => null],
            ],
            'profit' => 10, 'land' => 50_000_000,
        ], 2026);

        // 100 × 6 500 000 × 1.10 = 715 000 000; wear 15 / 80 = 18.75 % → 580 937 500.
        $this->assertSame(715_000_000.0, $result['rows'][0]['replacement']);
        $this->assertSame(18.75, $result['rows'][0]['physical']);
        $this->assertSame(580_937_500.0, $result['rows'][0]['value']);
        // A light structure lives 40 years: 20 / 40 = 50 %; with 10 % functional: 1 − 0.5 × 0.9 = 55 %.
        $this->assertSame(40, $result['rows'][1]['life']);
        $this->assertSame(55.0, $result['rows'][1]['depreciation']);
        $this->assertCount(2, $result['rows']);
        $this->assertSame(round(580_937_500 + 22_000_000 * 0.45 + 50_000_000, -3), $result['value']);
    }

    public function test_the_income_approach_capitalises_the_net_income(): void
    {
        $result = IncomeApproach::calculate(['area' => 100, 'rent' => 120_000, 'vacancy' => 10, 'opex' => 15, 'cap' => 14]);

        $this->assertSame(144_000_000.0, $result['pgi']);
        $this->assertSame(129_600_000.0, $result['egi']);
        $this->assertSame(110_160_000.0, $result['noi']);
        $this->assertSame(786_857_000.0, $result['value']);
    }

    public function test_both_are_saved_on_the_object_page_and_printed_in_the_report(): void
    {
        $this->actingAs($this->user)->get('/admin/real-estates/'.$this->house->id)->assertOk()
            ->assertSee('id="cost-approach"', false)->assertSee('id="income-approach"', false);

        $this->put('/admin/real-estates/'.$this->house->id.'/approaches/cost', [
            'buildings' => [
                ['name' => 'Asosiy bino', 'quantity' => '100', 'unit' => 'm2', 'rate' => '6 500 000', 'year_built' => '2011'],
                ['name' => '', 'quantity' => '', 'rate' => ''],
            ],
            'profit' => '10', 'land' => '50 000 000',
        ])->assertRedirect('/admin/real-estates/'.$this->house->id.'#cost-approach');
        $this->put('/admin/real-estates/'.$this->house->id.'/approaches/income', ['area' => '100', 'rent' => '120 000', 'vacancy' => '10', 'opex' => '15', 'cap' => '14'])
            ->assertRedirect();

        $house = $this->house->fresh();
        $this->assertCount(1, $house->report['cost']['buildings']);
        $this->assertSame('630938000.00', $house->value_cost);
        $this->assertSame('786857000.00', $house->value_income);

        $this->get('/admin/real-estates/'.$this->house->id.'/report?script=latn')->assertOk()
            ->assertSee('Asosiy bino')->assertSee('715 000 000')->assertSee('18,75')
            ->assertSee('Sof operatsion daromad')->assertSee('110 160 000');
    }

    public function test_wrong_data_is_refused_and_completed_valuations_are_protected(): void
    {
        $this->actingAs($this->user)->put('/admin/real-estates/'.$this->house->id.'/approaches/cost', ['buildings' => [['name' => 'Bino', 'quantity' => '100', 'rate' => '']]])
            ->assertSessionHasErrors('buildings.0.rate');
        $this->put('/admin/real-estates/'.$this->house->id.'/approaches/income', ['area' => '100', 'rent' => '1', 'cap' => '0'])->assertSessionHasErrors('cap');

        $this->house->update(['status' => ValuationStatus::Completed]);
        $this->actingAs(User::factory()->operator()->create())
            ->put('/admin/real-estates/'.$this->house->id.'/approaches/income', ['area' => '100', 'rent' => '1', 'cap' => '10'])->assertForbidden();
    }
}
