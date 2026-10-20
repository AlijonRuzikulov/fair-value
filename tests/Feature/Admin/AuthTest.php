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

    public function test_first_login_is_shown_on_a_trial_copy_until_the_password_is_changed(): void
    {
        config(['fairvalue.admin.email' => 'admin@fairvalue.uz', 'fairvalue.admin.password' => 'admin12345']);
        $admin = User::factory()->admin()->create(['email' => 'admin@fairvalue.uz', 'password' => 'admin12345']);

        config(['fairvalue.demo' => false]);
        $this->get('/login')->assertOk()->assertDontSee('admin12345')->assertDontSee('data-demo-fill', false);

        config(['fairvalue.demo' => true]);
        $this->get('/login')->assertOk()->assertSee('admin@fairvalue.uz')->assertSee('admin12345')->assertSee('data-demo-fill', false);

        $admin->update(['password' => 'my-own-password']);
        $this->get('/login')->assertOk()->assertDontSee('admin12345')->assertDontSee('data-demo-fill', false);
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

    public function test_a_forgotten_password_is_reset_in_the_terminal(): void
    {
        config(['fairvalue.admin.email' => 'admin@fairvalue.uz']);
        $admin = User::factory()->admin()->create(['email' => 'admin@fairvalue.uz', 'password' => 'forgotten-1', 'is_active' => false]);

        $this->artisan('user:password', ['--password' => 'short'])->assertExitCode(1);
        $this->artisan('user:password', ['email' => 'nobody@fairvalue.uz', '--password' => 'NewPassword2026'])
            ->expectsOutputToContain('admin@fairvalue.uz')->assertExitCode(1);
        $this->artisan('user:password')
            ->expectsQuestion('New password (at least 10 characters, letters and numbers)', 'NewPassword2026')
            ->expectsQuestion('The same password again', 'NewPassword2026')
            ->assertExitCode(0);

        $this->assertSame(1, Activity::where('event', 'password_changed')->count());
        $this->post('/login', ['email' => 'admin@fairvalue.uz', 'password' => 'NewPassword2026'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin);
    }
}
