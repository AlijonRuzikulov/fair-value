<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Map tiles are downloaded by the server once, with a User-Agent naming
 * the system, kept for a month and served to signed-in users.
 */
class MapTilesTest extends TestCase
{
    use RefreshDatabase;

    private string $png;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $image = imagecreatetruecolor(256, 256);
        ob_start();
        imagepng($image);
        $this->png = (string) ob_get_clean();
    }

    public function test_a_tile_is_downloaded_once_and_kept(): void
    {
        Http::fake(['tile.openstreetmap.org/*' => Http::response($this->png, 200, ['Content-Type' => 'image/png'])]);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/map/tiles/16/45379/24495.png')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/admin/map/tiles/16/45379/24495.png')->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://tile.openstreetmap.org/16/45379/24495.png'
            && str_starts_with($request->header('User-Agent')[0] ?? '', 'FairValue/1.0'));
        $this->assertTrue(Storage::disk('local')->exists('map-tiles/16/45379/24495'));
    }

    public function test_a_tile_that_cannot_be_downloaded_is_not_kept_and_old_ones_are_served(): void
    {
        Http::fake(['*' => Http::response('Access blocked', 403, ['Content-Type' => 'text/html'])]);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/map/tiles/10/700/380.png')->assertStatus(502);
        $this->assertFalse(Storage::disk('local')->exists('map-tiles/10/700/380'));

        Storage::disk('local')->put('map-tiles/10/700/381', $this->png);
        touch(Storage::disk('local')->path('map-tiles/10/700/381'), now()->subDays(40)->getTimestamp());
        $this->get('/admin/map/tiles/10/700/381.png')->assertOk();
    }

    public function test_only_signed_in_users_and_real_tiles(): void
    {
        Http::fake();
        $this->get('/admin/map/tiles/1/0/0.png')->assertRedirect('/login');

        $this->actingAs(User::factory()->create());
        $this->get('/admin/map/tiles/2/4/0.png')->assertNotFound();
        $this->get('/admin/map/tiles/20/0/0.png')->assertNotFound();
        Http::assertNothingSent();
    }
}
