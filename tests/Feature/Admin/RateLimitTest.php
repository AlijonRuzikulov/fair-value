<?php

namespace Tests\Feature\Admin;

use App\Models\District;
use App\Models\Landmark;
use App\Models\User;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Each limited address keeps its own count: the tiles of a map (dozens a page) must not use up
 * the allowance of the landmarks, the analogues or the PDF of the same user.
 */
class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_tiles_of_a_map_do_not_use_up_the_allowance_of_the_landmarks(): void
    {
        Storage::fake('local');
        $image = imagecreatetruecolor(256, 256);
        ob_start();
        imagepng($image);
        Http::fake(['tile.openstreetmap.org/*' => Http::response((string) ob_get_clean(), 200, ['Content-Type' => 'image/png'])]);
        $this->seed(RegionSeeder::class);
        $district = District::first();
        // A district with landmarks of its own: the list is answered without asking OpenStreetMap.
        Landmark::create(['district_id' => $district->id, 'name' => 'Bektemir bozori', 'kind' => 'portal']);
        $this->actingAs(User::factory()->create())->withSession(['locale' => 'uz']);

        // An object page with its map: forty tiles in the same minute.
        foreach (range(0, 39) as $i) {
            $this->get('/admin/map/tiles/16/'.(45379 + $i).'/24495.png')->assertOk();
        }

        $this->getJson('/admin/landmarks?district_id='.$district->id)->assertOk()->assertJsonPath('items.0.name', 'Bektemir bozori');
        $this->assertNotSame(429, $this->getJson('/admin/history')->status());
    }

    public function test_each_limit_counts_its_own_requests(): void
    {
        $this->actingAs(User::factory()->create());

        // Thirty landmark lookups reach the limit of the landmarks only.
        foreach (range(1, 30) as $i) {
            $this->getJson('/admin/landmarks')->assertOk();
        }
        $this->getJson('/admin/landmarks')->assertStatus(429);
        $this->assertNotSame(429, $this->getJson('/admin/history')->status());
    }
}
