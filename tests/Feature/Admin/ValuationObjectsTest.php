<?php

namespace Tests\Feature\Admin;

use App\Enums\ValuationStatus;
use App\Models\Activity;
use App\Models\Client;
use App\Models\District;
use App\Models\RealEstate;
use App\Models\User;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValuationObjectsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RegionSeeder::class);
        $this->user = User::factory()->create();
    }

    private function payload(array $overrides = []): array
    {
        $district = District::first();

        return array_merge([
            'kind' => 'nonresidential',
            'name' => 'Savdo do‘koni',
            'cadastral_number' => '10:01:05:01:02:0123',
            'total_area' => '1 250,5',
            'region_id' => $district->region_id,
            'district_id' => $district->id,
            'address' => 'Navoiy ko‘chasi, 5',
            'purpose' => 'collateral',
            'approaches' => ['cost', 'comparative'],
            'value_cost' => '1 200 000 000',
            'value_comparative' => '1 300 000 000',
            'value_income' => '999',
            'market_value' => '1 250 000 000',
            'service_fee' => '1 500 000',
            'status' => 'new',
            'features' => ['gas', 'water'],
            'persons' => [],
        ], $overrides);
    }

    public function test_real_estate_is_created_with_people_and_formatted_numbers(): void
    {
        $owner = Client::factory()->create();
        $customer = Client::factory()->legal()->create();

        $response = $this->actingAs($this->user)->post('/admin/real-estates', $this->payload([
            'persons' => [
                ['client_id' => $owner->id, 'role' => 'owner'],
                ['client_id' => $customer->id, 'role' => 'customer'],
                ['client_id' => '', 'role' => 'owner'],
            ],
        ]));

        $object = RealEstate::sole();
        $response->assertRedirect('/admin/real-estates/'.$object->id);

        $this->assertSame('1250.50', $object->total_area);
        $this->assertSame('1250000000.00', $object->market_value);
        $this->assertSame('1200000000.00', $object->value_cost);
        $this->assertNull($object->value_income, 'Values of approaches that were not applied are cleared.');
        $this->assertSame(['gas', 'water'], $object->features);
        $this->assertSame($this->user->id, $object->created_by);
        $this->assertMatchesRegularExpression('/^FV-[2-9A-Z]{4}-[2-9A-Z]{4}$/', $object->verify_code);
        $this->assertEqualsCanonicalizing(['owner', 'customer'], $object->clients->pluck('pivot.role')->all());
        $this->assertTrue(Activity::where('subject_type', 'real_estate')->where('event', 'created')->exists());

        $this->get('/admin/real-estates/'.$object->id)->assertOk()->assertSee('Savdo do‘koni');
    }

    public function test_completing_assigns_report_number_and_date(): void
    {
        $object = RealEstate::factory()->create(['status' => ValuationStatus::New, 'report_number' => null]);

        $this->actingAs($this->user)->put('/admin/real-estates/'.$object->id, $this->payload(['status' => 'completed']))
            ->assertRedirect();

        $object->refresh();
        $this->assertSame(ValuationStatus::Completed, $object->status);
        $this->assertMatchesRegularExpression('/^'.now()->year.'\/\d{4}$/', $object->report_number);
        $this->assertNotNull($object->completed_at);

        $next = RealEstate::factory()->create(['status' => ValuationStatus::Completed, 'report_number' => null]);
        $this->assertSame((int) substr($object->report_number, 5) + 1, (int) substr($next->report_number, 5));
    }

    public function test_district_must_belong_to_region(): void
    {
        $otherRegionDistrict = District::where('region_id', '!=', District::first()->region_id)->first();

        $this->actingAs($this->user)
            ->post('/admin/real-estates', $this->payload(['district_id' => $otherRegionDistrict->id]))
            ->assertSessionHasErrors('district_id');
    }

    public function test_list_filters_by_kind_status_and_search(): void
    {
        RealEstate::factory()->nonResidential()->create(['name' => 'Ombor A', 'status' => ValuationStatus::New]);
        RealEstate::factory()->nonResidential()->create(['name' => 'Ofis B', 'status' => ValuationStatus::Completed]);
        RealEstate::factory()->residential()->create(['name' => 'Kvartira C']);

        $this->actingAs($this->user)->get('/admin/real-estates?kind=nonresidential')
            ->assertSee('Ombor A')->assertSee('Ofis B')->assertDontSee('Kvartira C');

        $this->get('/admin/real-estates?kind=nonresidential&status=completed')
            ->assertSee('Ofis B')->assertDontSee('Ombor A');

        $this->get('/admin/real-estates?kind=nonresidential&q=Ombor')
            ->assertSee('Ombor A')->assertDontSee('Ofis B');
    }

    public function test_list_exports_to_csv(): void
    {
        RealEstate::factory()->nonResidential()->create(['name' => 'Eksport obyekti']);

        $response = $this->actingAs($this->user)->get('/admin/real-estates/export?kind=nonresidential');
        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Eksport obyekti', $csv);
    }

    public function test_every_object_type_can_be_created(): void
    {
        $common = ['status' => 'in_progress', 'approaches' => ['cost'], 'value_cost' => '100 000'];

        $this->actingAs($this->user);
        $this->post('/admin/assets', [...$common, 'name' => 'Stanok', 'quantity' => '2', 'unit' => 'pcs'])->assertRedirect();
        $this->post('/admin/vehicles', [...$common, 'brand' => 'Chevrolet', 'model' => 'Cobalt', 'mileage' => '45 000'])->assertRedirect();
        $this->post('/admin/other-assets', [...$common, 'name' => '30% ulush', 'category' => 'shares'])->assertRedirect();

        $this->assertDatabaseHas('assets', ['name' => 'Stanok', 'quantity' => 2]);
        $this->assertDatabaseHas('vehicles', ['brand' => 'Chevrolet', 'mileage' => 45000]);
        $this->assertDatabaseHas('other_assets', ['name' => '30% ulush']);
    }

    public function test_new_object_from_client_card_gets_the_client_as_owner(): void
    {
        $client = Client::factory()->create(['last_name' => 'Egasi']);

        $this->actingAs($this->user)->get('/admin/vehicles/create?client='.$client->id)
            ->assertOk()
            ->assertSee('name="persons[0][client_id]" value="'.$client->id.'"', false);
    }
}
