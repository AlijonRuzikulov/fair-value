<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * "php artisan share" (LINK-OCHISH.bat) gives a temporary internet link to
 * the system running on this computer, through a Cloudflare quick tunnel.
 */
class ShareLinkTest extends TestCase
{
    use RefreshDatabase;

    /** A stand-in for cloudflared that prints what it prints and stops. */
    private function fakeTunnel(string $output): string
    {
        $path = tempnam(sys_get_temp_dir(), 'cloudflared');
        file_put_contents($path, "#!/bin/sh\nprintf '%s\\n' ".escapeshellarg($output)." >&2\n");
        chmod($path, 0755);
        config(['fairvalue.share.cloudflared' => $path]);

        return $path;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['fairvalue.admin.password' => 'admin12345']);
    }

    /** The system answers on its port, or not. */
    private function systemRuns(bool $runs = true): void
    {
        Http::fake(['127.0.0.1:8000/up' => Http::response('ok', $runs ? 200 : 500)]);
    }

    public function test_the_link_is_shown_to_be_sent(): void
    {
        User::factory()->admin()->create(['password' => 'Yangi-parol-2026']);
        $this->systemRuns();
        $tunnel = $this->fakeTunnel("2026-11-08T10:00:00Z INF |  https://quiet-river-42.trycloudflare.com  |\nINF Registered tunnel connection");

        $this->artisan('share')->expectsOutputToContain('https://quiet-river-42.trycloudflare.com')->assertSuccessful();
        unlink($tunnel);
    }

    public function test_a_cloudflared_downloaded_in_the_browser_is_found_in_the_folder(): void
    {
        User::factory()->admin()->create(['password' => 'Yangi-parol-2026']);
        $this->systemRuns();
        $byHand = base_path('cloudflared-linux-amd64');
        rename($this->fakeTunnel('INF |  https://by-hand-7.trycloudflare.com  |'), $byHand);
        config(['fairvalue.share.cloudflared' => null]);

        try {
            $this->artisan('share')->expectsOutputToContain('https://by-hand-7.trycloudflare.com')->assertSuccessful();
        } finally {
            unlink($byHand);
        }
    }

    public function test_nothing_is_opened_while_the_first_password_is_in_use(): void
    {
        User::factory()->admin()->create(['password' => 'admin12345']);
        $this->systemRuns();
        $tunnel = $this->fakeTunnel('INF |  https://never-shown.trycloudflare.com  |');

        $this->artisan('share')->doesntExpectOutputToContain('trycloudflare.com')->assertFailed();
        unlink($tunnel);
    }

    public function test_the_tunnel_must_give_a_link(): void
    {
        User::factory()->admin()->create(['password' => 'Yangi-parol-2026']);
        $this->systemRuns();
        $tunnel = $this->fakeTunnel('ERR failed to request quick Tunnel');

        $this->artisan('share')->expectsOutputToContain('failed to request quick Tunnel')->assertFailed();
        unlink($tunnel);
    }

    public function test_the_system_must_be_running(): void
    {
        User::factory()->admin()->create(['password' => 'Yangi-parol-2026']);
        $this->systemRuns(false);
        $tunnel = $this->fakeTunnel('INF |  https://never-shown.trycloudflare.com  |');

        $this->artisan('share')->doesntExpectOutputToContain('trycloudflare.com')->assertFailed();
        unlink($tunnel);
    }

    public function test_links_of_the_tunnel_open_over_https(): void
    {
        // The tunnel runs on this computer: its forwarded https and host are trusted, nobody else's.
        $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'quiet-river-42.trycloudflare.com'])
            ->get('/login')->assertOk()->assertSee('https://quiet-river-42.trycloudflare.com/login', false);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'evil.example'])
            ->get('/login')->assertOk()->assertDontSee('evil.example');
    }
}
