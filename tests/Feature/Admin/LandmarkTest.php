<?php

namespace Tests\Feature\Admin;

use App\Models\District;
use App\Models\Landmark;
use App\Models\RealEstate;
use App\Models\User;
use Database\Seeders\LandmarkSeeder;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The landmarks (mo'ljal) of a district come in the list of the landmark box
 * as soon as the district is chosen: those typed before, then the portal's
 * list of the district, or OpenStreetMap's where the portal has none.
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

        $this->actingAs($this->user)->withSession(['locale' => 'uz'])->getJson('/admin/landmarks?district_id='.$this->district->id)
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

    public function test_the_portal_landmarks_of_the_district_are_listed_without_the_map(): void
    {
        Http::fake();
        $this->seed(LandmarkSeeder::class);
        $district = District::where('name_uz', 'Yunusobod tumani')->firstOrFail();
        $place = ['district_id' => $district->id, 'region_id' => $district->region_id];
        RealEstate::factory()->count(2)->create($place + ['landmark' => 'Feruza massivi']);
        RealEstate::factory()->create($place + ['landmark' => 'Yunusobod 1-Mavze']);
        $list = fn (string $locale) => collect($this->actingAs($this->user)->withSession(['locale' => $locale])
            ->getJson('/admin/landmarks?district_id='.$district->id)->assertOk()->assertJsonPath('failed', false)->json('items'));

        // Typed before first, then the portal's list in its own order, without a kind of its own,
        // in the alphabet of the language; a name typed before in the other alphabet comes once.
        $uz = $list('uz');
        $this->assertSame(['Feruza massivi', 'Yunusobod 1-Mavze', 'Alayskiy', 'Yunusobod 2-Mavze'], $uz->pluck('name')->take(4)->all());
        $this->assertSame('', $uz[2]['kind']);
        $this->assertSame(1, $uz->where('name', 'Yunusobod 1-Mavze')->count());
        $this->assertGreaterThan(200, $uz->count());
        $this->assertSame(['Феруза массиви', 'Юнусобод 1-Мавзе', 'Алайский', 'Юнусобод 2-Мавзе'], $list('oz')->pluck('name')->take(4)->all());
        $this->assertSame(['Feruza massivi', 'Yunusobod 1-Mavze', 'Алайский', 'Юнусобод 2-Мавзе'], $list('ru')->pluck('name')->take(4)->all());
        Http::assertNothingSent();
    }

    public function test_the_portal_list_is_added_once_and_keeps_a_place_found_on_the_map(): void
    {
        $district = District::where('name_uz', 'Yunusobod tumani')->firstOrFail();
        Landmark::create(['district_id' => $district->id, 'name' => 'Юнусобод 1-Мавзе', 'kind' => 'area', 'latitude' => 41.36, 'longitude' => 69.28]);

        $this->seed(LandmarkSeeder::class);
        $count = Landmark::where('kind', 'portal')->count();
        $this->seed(LandmarkSeeder::class);
        LandmarkSeeder::import();

        $this->assertSame($count, Landmark::where('kind', 'portal')->count());
        $this->assertGreaterThan(18000, $count);
        $kept = Landmark::where('district_id', $district->id)->where('name', 'Юнусобод 1-Мавзе')->sole();
        $this->assertSame(['portal', 41.36, 69.28], [$kept->kind, $kept->latitude, $kept->longitude]);
        // Every district but Davlatobod has the portal's list.
        $this->assertSame(District::count() - 1, District::whereHas('landmarks', fn ($q) => $q->where('kind', 'portal'))->count());
    }

    public function test_the_landmark_of_an_object_is_shown_in_the_alphabet_of_the_language(): void
    {
        $item = RealEstate::factory()->create(['district_id' => $this->district->id, 'region_id' => $this->district->region_id, 'landmark' => 'Юнусобод 1-Мавзе']);

        $this->actingAs($this->user)->withSession(['locale' => 'uz'])->get('/admin/real-estates/'.$item->id.'/edit')->assertOk()
            ->assertSee('value="Yunusobod 1-Mavze"', false);
        $this->actingAs($this->user)->withSession(['locale' => 'oz'])->get('/admin/real-estates/'.$item->id.'/edit')->assertOk()
            ->assertSee('value="Юнусобод 1-Мавзе"', false);
    }
}
