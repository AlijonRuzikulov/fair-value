<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Behind Cloudflare or Codespaces the site must build HTTPS links on the
 * public domain; without a trusted proxy forwarded headers are ignored.
 */
class TrustedProxyTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = [
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'fairvalue.uz',
        'X-Forwarded-Port' => '443',
        'X-Forwarded-For' => '203.0.113.7',
    ];

    public function test_forwarded_headers_are_used_when_the_proxy_is_trusted(): void
    {
        config(['trustedproxy.proxies' => '*']);

        $this->get('/', self::HEADERS)
            ->assertOk()
            ->assertSee('https://fairvalue.uz/css/site.css', false);

        $this->post('/request', ['name' => 'Test', 'phone' => '+998 90 000-00-00'], self::HEADERS);
        $this->assertDatabaseHas('leads', ['ip_address' => '203.0.113.7']);
    }

    public function test_forwarded_headers_are_ignored_without_a_trusted_proxy(): void
    {
        config(['trustedproxy.proxies' => null]);

        $this->get('/', self::HEADERS)
            ->assertOk()
            ->assertDontSee('https://fairvalue.uz/css/site.css', false);
    }
}
