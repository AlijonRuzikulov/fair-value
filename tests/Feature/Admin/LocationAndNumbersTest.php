<?php

namespace Tests\Feature\Admin;

use App\Enums\ValuationStatus;
use App\Http\Controllers\Admin\SettingController;
use App\Models\District;
use App\Models\RealEstate;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\DocumentNumber;
use App\Support\Format;
use App\Support\StaticMap;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The point of real estate on the map, its cadastral value, payments for
 * the service, and contract and report numbers given automatically.
 */
class LocationAndNumbersTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-20 10:00');
        $this->seed(RegionSeeder::class);
        $this->user = User::factory()->create();
    }

    private function payload(array $overrides = []): array
    {
        $district = District::first();

        return array_merge([
            'kind' => 'residential', 'name' => 'Turar joy', 'total_area' => '61,52', 'region_id' => $district->region_id,
            'district_id' => $district->id, 'address' => 'Feruza ko‘chasi, 26-uy', 'purpose' => 'collateral', 'status' => 'new',
            'service_fee' => '1 500 000', 'persons' => [],
        ], $overrides);
    }

    public function test_contract_numbers_continue_the_sequence_and_report_numbers_follow_the_company_format(): void
    {
        $this->actingAs($this->user)->post('/admin/real-estates', $this->payload())->assertRedirect();
        $first = RealEstate::latest('id')->first();
        $this->assertSame('1-26', $first->contract_number);
        $this->assertSame('2026-10-20', $first->contract_date->toDateString());

        // A number typed by hand (moving from another system) is continued, across object types.
        Vehicle::factory()->create(['contract_number' => '668-26', 'status' => ValuationStatus::InProgress, 'report_number' => null]);
        $this->post('/admin/real-estates', $this->payload())->assertRedirect();
        $this->assertSame('669-26', RealEstate::latest('id')->value('contract_number'));
        $this->post('/admin/real-estates', $this->payload(['contract_number' => 'B-15']))->assertRedirect();
        $this->assertSame('B-15', RealEstate::latest('id')->value('contract_number'));

        $this->get('/admin/real-estates/create?kind=residential')->assertOk()->assertSee('placeholder="670-26"', false)->assertSee('data-area-copy', false);

        // Reports: the default format, then the company's own.
        $first->update(['status' => ValuationStatus::Completed]);
        $this->assertSame('2026/0001', $first->fresh()->report_number);

        Setting::put(['report_number_format' => '20/{n}-{yy}']);
        RealEstate::factory()->create(['report_number' => '20/550-26']);
        $next = RealEstate::factory()->create(['status' => ValuationStatus::InProgress, 'report_number' => null]);
        $next->update(['status' => ValuationStatus::Completed]);
        $this->assertSame('20/551-26', $next->fresh()->report_number);
    }

    public function test_a_number_format_needs_a_place_for_the_sequence(): void
    {
        $this->assertTrue(DocumentNumber::valid('{n}-{yy}'));
        $this->assertTrue(DocumentNumber::valid('{yyyy}/{nnnn}'));
        $this->assertFalse(DocumentNumber::valid('{yy}'));
        $this->assertFalse(DocumentNumber::valid('{n}/{n}'));

        $admin = User::factory()->admin()->create();
        $settings = collect(SettingController::KEYS)->mapWithKeys(fn ($key) => [$key => Setting::get($key)])->all();
        $this->actingAs($admin)->put('/admin/settings', [...$settings, 'contract_number_format' => 'SH-{yy}'])
            ->assertSessionHasErrors('contract_number_format');
        $this->put('/admin/settings', [...$settings, 'contract_number_format' => 'SH-{n}/{yyyy}'])->assertSessionHasNoErrors();
        $this->assertSame('SH-1/2026', DocumentNumber::next('contract'));
    }

    public function test_the_point_on_the_map_and_the_new_fields_are_saved_shown_and_printed(): void
    {
        $this->actingAs($this->user)->post('/admin/real-estates', $this->payload([
            'latitude' => '41.3111', 'longitude' => '69.2797', 'landmark' => 'Feruza massivi',
            'cadastral_value' => '830 176 946,46', 'built_up_area' => '61,52', 'paid_amount' => '500 000',
        ]))->assertRedirect();
        $flat = RealEstate::sole();

        $this->assertEqualsWithDelta(41.3111, $flat->latitude, 0.000001);
        $this->assertSame('830176946.46', $flat->cadastral_value);
        $this->assertSame('61.52', $flat->built_up_area);

        $this->get('/admin/real-estates/'.$flat->id)->assertOk()
            ->assertSee('/admin/map/tiles/16/45379/24495.png', false)
            ->assertSee('yandex.uz/maps/?pt=69.2797,41.3111', false)
            ->assertSee('Feruza massivi')->assertSee(Format::money(830176946.46))
            ->assertSee(Format::money(1000000));  // the debt

        $this->get('/admin/real-estates/'.$flat->id.'/edit')->assertOk()
            ->assertSee('data-map-picker', false)->assertSee('leaflet/leaflet.js', false)->assertSee('<option value="Feruza massivi">', false);

        $this->get('/admin/real-estates/'.$flat->id.'/report?script=latn')->assertOk()
            ->assertSee('Geografik koordinatalari')->assertSee('41.311100, 69.279700')
            ->assertSee('Qurilish osti maydoni')->assertSee('mo‘ljal', false)->assertSee('/admin/map/tiles/16/', false)
            // Paged.js waits for every image; a lazy tile outside the screen never loads and the report hangs.
            ->assertSee('loading="eager"', false)->assertDontSee('loading="lazy"', false);
    }

    public function test_coordinates_come_in_pairs_and_within_range(): void
    {
        $this->actingAs($this->user)->post('/admin/real-estates', $this->payload(['latitude' => '41.3']))->assertSessionHasErrors('longitude');
        $this->post('/admin/real-estates', $this->payload(['latitude' => '141', 'longitude' => '69']))->assertSessionHasErrors('latitude');
        $this->assertSame(0, RealEstate::count());
    }

    public function test_tiles_cover_the_map_and_the_tile_server_is_allowed(): void
    {
        $tiles = StaticMap::tiles(41.3111, 69.2797, 16, 640, 300);
        foreach ([[0, 0], [639, 0], [0, 299], [639, 299], [320, 150]] as [$x, $y]) {
            $covered = collect($tiles)->contains(fn ($t) => $x >= $t['left'] && $x < $t['left'] + 256 && $y >= $t['top'] && $y < $t['top'] + 256);
            $this->assertTrue($covered, "Pixel {$x},{$y} is covered.");
        }

        config(['app.debug' => false]);
        $response = $this->actingAs($this->user)->get('/admin');
        $this->assertStringContainsString("img-src 'self' data:;", $response->headers->get('Content-Security-Policy'), 'Tiles come from this site, not from the tile server.');
        $this->assertStringContainsString('geolocation=(self)', $response->headers->get('Permissions-Policy'));
    }
}
