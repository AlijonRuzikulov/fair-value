<?php

namespace Tests\Feature\Admin;

use App\Models\RealEstate;
use App\Models\User;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The address search of the map on the form of real estate, and the grey
 * layer of the map.
 */
class MapSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_places_are_found_once_and_kept(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([
            ['display_name' => 'Feruza ko‘chasi, Mirzo Ulug‘bek tumani, Toshkent', 'lat' => '41.3399', 'lon' => '69.3349'],
            ['display_name' => 'Feruza massivi, Toshkent', 'lat' => '41.3410', 'lon' => '69.3300', 'extra' => 'x'],
            ['lat' => '1'],
        ])]);
        $this->actingAs(User::factory()->create());

        $this->getJson('/admin/map/search?q=Feruza 26')->assertOk()->assertExactJson([
            ['label' => 'Feruza ko‘chasi, Mirzo Ulug‘bek tumani, Toshkent', 'lat' => 41.3399, 'lng' => 69.3349],
            ['label' => 'Feruza massivi, Toshkent', 'lat' => 41.341, 'lng' => 69.33],
        ]);
        $this->getJson('/admin/map/search?q=feruza 26')->assertOk()->assertJsonCount(2);

        Http::assertSentCount(1);   // the second search came from the cache
        Http::assertSent(fn (Request $request) => $request['countrycodes'] === 'uz' && str_starts_with($request->header('User-Agent')[0], 'FairValue/'));
        $this->getJson('/admin/map/search?q=ab')->assertOk()->assertExactJson([]);
    }

    public function test_a_search_that_did_not_answer_is_told_and_not_kept(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::sequence()->push('', 503)->push([], 200)]);
        $this->actingAs(User::factory()->create());

        $this->getJson('/admin/map/search?q=Chirchiq')->assertStatus(502)->assertJsonStructure(['message']);
        $this->getJson('/admin/map/search?q=Chirchiq')->assertOk()->assertExactJson([]);   // asked again
        Http::assertSentCount(2);
    }

    public function test_the_form_has_the_search_the_grey_layer_and_the_value_types(): void
    {
        $this->seed(RegionSeeder::class);
        $this->actingAs($user = User::factory()->create());
        $flat = RealEstate::factory()->residential()->create(['appraiser_id' => $user->id]);

        $this->withSession(['locale' => 'uz'])->get('/admin/real-estates/'.$flat->id.'/edit')->assertOk()
            ->assertSee('data-map-query', false)->assertSee('Kulrang xarita')->assertSee(route('admin.map.search'), false);
        $this->withSession(['locale' => 'uz'])->get('/admin/real-estates/'.$flat->id.'/report/edit')->assertOk()
            ->assertSee('<datalist id="opt-value_type">', false)->assertSee('<option value="Tugatish qiymati">', false);
    }
}
