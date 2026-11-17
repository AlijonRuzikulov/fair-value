<?php

namespace Tests\Feature\Analogs;

use App\Enums\ValuationStatus;
use App\Models\Analog;
use App\Models\CurrencyRate;
use App\Models\RealEstate;
use App\Models\User;
use App\Support\Format;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The comparative approach on the object page is the valuation portal's grid, and the land counts in the value. */
class ComparativeGridTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_grid_keeps_what_is_typed_and_adds_the_land(): void
    {
        $this->seed(RegionSeeder::class);
        CurrencyRate::create(['code' => 'USD', 'nominal' => 1, 'rate' => 12500, 'diff' => 0, 'rate_date' => now()->toDateString()]);
        $user = User::factory()->create();
        $house = RealEstate::factory()->residential()->create([
            'total_area' => 100, 'land_area' => 500, 'status' => ValuationStatus::InProgress, 'approaches' => ['comparative'],
            'report' => ['repair' => 'Qoniqarli', 'completion' => 100],
        ]);
        $analog = $house->analogs()->create(['status' => Analog::READY, 'title' => 'Uy, Chirchiq', 'price' => 1_000_000_000, 'currency' => 'UZS',
            'price_uzs' => 1_000_000_000, 'area' => 100, 'included' => true, 'condition' => 'Qoniqarli', 'adjustments' => ['bargain' => -5]]);

        $this->actingAs($user)->withSession(['locale' => 'uz'])->put('/admin/real-estates/'.$house->id.'/analogs', [
            'comparative' => ['land_price' => '1 000 000', 'land_coef' => '0,23', 'size_coef' => ''],
            'analogs' => [$analog->id => [
                'price' => '1 000 000 000', 'currency' => 'UZS', 'area' => '100', 'included' => '1', 'condition' => 'Qoniqarli',
                'land_area' => '450', 'land_coef' => '0,23', 'size_coef' => '', 'completion' => '',
                'adjustments' => ['bargain' => '0', 'location' => '10'],
            ]],
        ])->assertRedirect('/admin/real-estates/'.$house->id.'#analogs')->assertSessionHas('status');

        $house->refresh();
        $analog->refresh();
        $this->assertEquals(['size_coef' => null, 'land_coef' => 0.23, 'land_price' => 1_000_000], $house->report['comparative']);
        $this->assertEquals(450, $analog->adjustments['land_area']);
        $this->assertEquals(10, $analog->adjustments['location']);
        $this->assertNull($analog->adjustments['size_coef'], 'Empty: the coefficient follows the area.');
        $this->assertNotNull($analog->usd_rate, 'The rate of the day is kept for a price in sum too.');
        // 10 000 000 × 1.10 per m² × 100 m² + 1 000 000 × 500 m² of land.
        $this->assertEqualsWithDelta(1_100_000_000 + 500_000_000, (float) $house->value_comparative, 0.01);

        $this->get('/admin/real-estates/'.$house->id)->assertOk()
            ->assertSeeInOrder(['Taqqoslash elementlari', 'Baholash obyekti', 'analog 1', 'Sotish bahosi', 'Topshirilayotgan mulk huquqi',
                'Sotuv shartlari', 'Bozor shartlari', 'Obyektning joylashgan yeri', 'Fizik tavsif farqiga qarab tuzatish (binoning katta-kichikligi)',
                'Bino holati bo‘yicha', 'Yer uchastkasining hajmi va unda qurilgan imoratlar maydoni', 'Qulayliklari', 'Tuzatishlar soni',
                'Koeffitsient ulushlari', 'Baholash obyektining 1 m² uchun yakuniy qiymati', 'Yer uchastkasi qiymati', Format::number(500_000_000, 2),
                'Baholash obyekti umumiy qiymati', Format::number(1_600_000_000, 2)])
            ->assertSee('name="comparative[land_price]"', false)
            ->assertSee('placeholder="0.26"', false);
    }
}
