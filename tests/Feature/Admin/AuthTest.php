<?php

namespace Tests\Feature\Admin;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->get('/login')->assertOk();
    }

    public function test_demo_login_is_shown_only_in_demo_mode(): void
    {
        config(['fairvalue.demo' => false]);
        $this->get('/login')->assertOk()->assertDontSee('demo12345')->assertDontSee('data-demo-fill', false);

        config(['fairvalue.demo' => true]);
        $this->get('/login')->assertOk()->assertSee('demo@fairvalue.uz')->assertSee('demo12345')->assertSee('data-demo-fill', false);
    }

    public function test_employee_can_sign_in_and_out(): void
    {
        $user = User::factory()->create(['email' => 'a@fairvalue.uz', 'password' => 'secret123']);

        $this->post('/login', ['email' => 'a@fairvalue.uz', 'password' => 'secret123'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertTrue(Activity::where('event', 'login')->where('user_id', $user->id)->exists());

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'a@fairvalue.uz', 'password' => 'secret123']);

        $this->post('/login', ['email' => 'a@fairvalue.uz', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_deactivated_employee_cannot_sign_in_or_stay_signed_in(): void
    {
        $user = User::factory()->create(['email' => 'b@fairvalue.uz', 'password' => 'secret123', 'is_active' => false]);

        $this->post('/login', ['email' => 'b@fairvalue.uz', 'password' => 'secret123'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($user)->get('/admin')->assertRedirect('/login');
        $this->assertGuest();
    }
}
