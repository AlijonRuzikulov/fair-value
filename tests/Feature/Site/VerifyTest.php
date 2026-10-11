<?php

namespace Tests\Feature\Site;

use App\Enums\ValuationStatus;
use App\Models\Client;
use App\Models\RealEstate;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RegionSeeder::class);
        $this->withSession(['locale' => 'uz']);
    }

    public function test_completed_report_is_confirmed_without_personal_data(): void
    {
        $object = RealEstate::factory()->create(['status' => ValuationStatus::Completed, 'market_value' => 1250000000]);
        $client = Client::factory()->create(['last_name' => 'Maxfiyov']);
        $object->clients()->attach($client->id, ['role' => 'owner']);

        $this->get('/verify?code='.$object->verify_code)
            ->assertOk()
            ->assertSee('Hisobot haqiqiy')
            ->assertSee($object->report_number)
            ->assertSee("1\u{00A0}250\u{00A0}000\u{00A0}000")
            ->assertDontSee('Maxfiyov');
    }

    public function test_code_is_normalised(): void
    {
        $object = RealEstate::factory()->create(['status' => ValuationStatus::Completed]);
        $loose = strtolower(str_replace('-', ' ', substr($object->verify_code, 3)));

        $this->get('/verify?code='.urlencode($loose))->assertOk()->assertSee('Hisobot haqiqiy');
        $this->get('/v/'.$object->verify_code)->assertRedirect('/verify?code='.$object->verify_code);
    }

    public function test_unfinished_valuation_shows_no_value(): void
    {
        $object = RealEstate::factory()->create(['status' => ValuationStatus::InProgress, 'market_value' => 999000000]);

        $this->get('/verify?code='.$object->verify_code)
            ->assertOk()
            ->assertSee('Baholash hali yakunlanmagan')
            ->assertDontSee("999\u{00A0}000\u{00A0}000");
    }

    public function test_unknown_or_deleted_report_is_not_found(): void
    {
        $object = RealEstate::factory()->create(['status' => ValuationStatus::Completed]);
        $object->delete();

        $this->get('/verify?code='.$object->verify_code)->assertOk()->assertSee('Hisobot topilmadi');
        $this->get('/verify?code=FV-AAAA-BBBB')->assertOk()->assertSee('Hisobot topilmadi');
    }
}
