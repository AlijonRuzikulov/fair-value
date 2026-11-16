<?php

namespace Tests\Feature\Admin;

use App\Models\District;
use App\Models\Landmark;
use App\Models\RealEstate;
use App\Models\User;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The landmarks (mo'ljal) of a district come in the list of the landmark box
 * as soon as the district is chosen: those typed before, then OpenStreetMap's.
 */
class LandmarkTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private District $district;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RegionSeeder::class);
        $this->user = User::factory()->create();
        $this->district = District::with('region')->first();
    }

    /** What Overpass answers for the district (nodes with lat/lon, ways and relations with a centre). */
    private function elements(): array
    {
        return [
            ['type' => 'relation', 'id' => 1, 'center' => ['lat' => 41.28, 'lon' => 69.20], 'tags' => ['boundary' => 'administrative', 'admin_level' => '8', 'name' => 'Бунёдкор', 'name:uz' => 'Bunyodkor mahallasi']],
            ['type' => 'node', 'id' => 2, 'lat' => 41.281, 'lon' => 69.211, 'tags' => ['place' => 'quarter', 'name' => 'Chilonzor-9']],
            ['type' => 'way', 'id' => 3, 'center' => ['lat' => 41.282, 'lon' => 69.212], 'tags' => ['landuse' => 'residential', 'name' => 'Chilonzor-9']],
            ['type' => 'way', 'id' => 4, 'center' => ['lat' => 41.29, 'lon' => 69.22], 'tags' => ['landuse' => 'residential', 'name' => 'Qatortol']],
            ['type' => 'node', 'id' => 5, 'lat' => 41.30, 'lon' => 69.23, 'tags' => ['amenity' => 'marketplace', 'name' => 'Olmazor bozori']],
            ['type' => 'node', 'id' => 6, 'lat' => 41.27, 'lon' => 69.20, 'tags' => ['railway' => 'station', 'station' => 'subway', 'name' => 'Chilonzor']],
            ['type' => 'node', 'id' => 7, 'lat' => 41.26, 'lon' => 69.19, 'tags' => ['amenity' => 'pharmacy', 'name' => 'Dorixona']],
            ['type' => 'node', 'id' => 8, 'lat' => 41.25, 'lon' => 69.18, 'tags' => ['place' => 'quarter']],
        ];
    }

    private function fakeMap(int $overpassStatus = 200, int $mirrorStatus = 200): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['osm_type' => 'node', 'osm_id' => 5, 'category' => 'place', 'type' => 'suburb'],
                ['osm_type' => 'relation', 'osm_id' => 2441810, 'category' => 'boundary', 'type' => 'administrative'],
            ]),
            'overpass-api.de/*' => Http::response($overpassStatus === 200 ? ['elements' => $this->elements()] : 'busy', $overpassStatus),
            'overpass.kumi.systems/*' => Http::response($mirrorStatus === 200 ? ['elements' => $this->elements()] : 'busy', $mirrorStatus),
        ]);
    }

    public function test_the_landmarks_of_the_district_are_listed_and_kept(): void
    {
        $this->fakeMap();
        RealEstate::factory()->count(2)->create(['district_id' => $this->district->id, 'region_id' => $this->district->region_id, 'landmark' => 'Feruza massivi']);

        $response = $this->actingAs($this->user)->withSession(['locale' => 'uz'])
            ->getJson('/admin/landmarks?district_id='.$this->district->id)->assertOk()->assertJsonPath('failed', false);
        $items = collect($response->json('items'));

        // Typed before first, then by kind: the mahalla, the massifs, the metro, the market; one entry per name.
        $this->assertSame(['Feruza massivi', 'Bunyodkor mahallasi', 'Chilonzor-9', 'Qatortol', 'Chilonzor', 'Olmazor bozori'], $items->pluck('name')->all());
        $this->assertSame(['Avval kiritilgan', 'Mahalla', 'Massiv, kvartal', 'Massiv, kvartal', 'Metro bekati', 'Bozor'], $items->pluck('kind')->all());
        $this->assertSame([41.281, 69.211], [$items[2]['lat'], $items[2]['lng']]);
        $this->assertNull($items[0]['lat']);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'overpass-api.de') && str_contains((string) $r['data'], 'area(3602441810)'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'nominatim') && $r['countrycodes'] === 'uz' && str_contains($r['q'], $this->district->name_uz));

        // Kept: the next time nothing is asked of OpenStreetMap.
        $this->assertSame(5, Landmark::where('district_id', $this->district->id)->count());
        Http::fake();
        $this->getJson('/admin/landmarks?district_id='.$this->district->id)->assertOk()->assertJsonCount(6, 'items');
        Http::assertNothingSent();
    }

    public function test_the_second_server_is_asked_when_the_first_does_not_answer(): void
    {
        $this->fakeMap(overpassStatus: 504);

        $this->actingAs($this->user)->getJson('/admin/landmarks?district_id='.$this->district->id)
            ->assertOk()->assertJsonPath('failed', false)->assertJsonPath('items.0.name', 'Bunyodkor mahallasi');
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'overpass.kumi.systems'));
    }

    public function test_when_the_map_does_not_answer_the_landmark_is_typed_and_it_is_not_asked_again_at_once(): void
    {
        $this->fakeMap(overpassStatus: 504, mirrorStatus: 504);

        $this->actingAs($this->user)->getJson('/admin/landmarks?district_id='.$this->district->id)
            ->assertOk()->assertJsonPath('failed', true)->assertJsonCount(0, 'items');
        $this->assertNull($this->district->fresh()->landmarks_fetched_at);

        Http::fake();
        $this->getJson('/admin/landmarks?district_id='.$this->district->id)->assertOk()->assertJsonPath('failed', true);
        Http::assertNothingSent();
    }

    public function test_the_landmark_box_asks_for_the_list(): void
    {
        $this->actingAs($this->user)->withSession(['locale' => 'uz'])->get('/admin/real-estates/create?kind=residential')->assertOk()
            ->assertSee('data-landmarks-url="'.route('admin.landmarks').'"', false)
            ->assertSee('Tumanda :count ta mo‘ljal: ro‘yxatdan tanlang yoki yozing.');
        $this->getJson('/admin/landmarks?district_id=999999')->assertOk()->assertExactJson(['items' => [], 'failed' => false]);
    }
}
