<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_send_protective_headers_and_inline_scripts_carry_the_nonce(): void
    {
        config(['app.debug' => false]);

        $response = $this->get('/')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-([^']+)'/", $csp);
        preg_match("/'nonce-([^']+)'/", $csp, $m);
        $response->assertSee('<script nonce="'.$m[1].'">', false);

        $this->get('/no-such-page')->assertNotFound()->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_admin_is_warned_until_the_initial_password_is_changed(): void
    {
        config(['fairvalue.admin.password' => 'admin12345']);
        User::factory()->admin()->create(['email' => 'admin@fairvalue.uz', 'password' => 'admin12345']);
        $warning = 'class="security-note"';

        $this->post('/login', ['email' => 'admin@fairvalue.uz', 'password' => 'admin12345'])->assertRedirect('/admin');
        $this->get('/admin')->assertOk()->assertSee($warning, false);

        $this->put('/admin/profile', [
            'name' => 'Administrator',
            'email' => 'admin@fairvalue.uz',
            'current_password' => 'admin12345',
            'password' => 'A-much-better-one-2026',
            'password_confirmation' => 'A-much-better-one-2026',
        ])->assertRedirect();

        $this->get('/admin')->assertOk()->assertDontSee($warning, false);
    }

    public function test_own_password_does_not_trigger_the_warning(): void
    {
        config(['fairvalue.admin.password' => 'admin12345']);
        User::factory()->create(['email' => 'a@fairvalue.uz', 'password' => 'secret-password-1']);

        $this->post('/login', ['email' => 'a@fairvalue.uz', 'password' => 'secret-password-1'])->assertRedirect('/admin');
        $this->get('/admin')->assertOk()->assertDontSee('class="security-note"', false);
    }
}
