<?php

namespace Tests\Feature\Admin;

use App\Enums\ValuationStatus;
use App\Models\Activity;
use App\Models\RealEstate;
use App\Models\SparePart;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_guessing_is_limited_per_account(): void
    {
        User::factory()->create(['email' => 'a@fairvalue.uz', 'password' => 'secret-password-1']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'a@fairvalue.uz', 'password' => 'wrong'.$i])->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => 'A@fairvalue.uz', 'password' => 'secret-password-1'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_changing_a_password_signs_out_other_sessions(): void
    {
        $user = User::factory()->create(['password' => 'secret-password-1']);

        $this->actingAs($user)->get('/admin')->assertOk();

        // The administrator resets the password, e.g. after a leak.
        $user->forceFill(['password' => 'another-password-2'])->save();

        $this->get('/admin')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_changing_the_login_requires_the_current_password(): void
    {
        $user = User::factory()->create(['email' => 'a@fairvalue.uz', 'password' => 'secret-password-1']);

        $this->actingAs($user)->put('/admin/profile', ['name' => 'A', 'email' => 'thief@example.com'])
            ->assertSessionHasErrors('current_password');
        $this->assertSame('a@fairvalue.uz', $user->fresh()->email);

        $this->put('/admin/profile', ['name' => 'A', 'email' => 'new@fairvalue.uz', 'current_password' => 'secret-password-1'])
            ->assertSessionHasNoErrors();
        $this->assertSame('new@fairvalue.uz', $user->fresh()->email);
    }

    public function test_short_passwords_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Xodim', 'email' => 'x@fairvalue.uz', 'role' => 'appraiser', 'is_active' => 1,
            'password' => '12345678', 'password_confirmation' => '12345678',
        ])->assertSessionHasErrors('password');
    }

    public function test_employee_changes_are_logged_without_the_password(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->put('/admin/users/'.$user->id, [
            'name' => $user->name, 'email' => $user->email, 'role' => 'admin', 'is_active' => 1,
            'password' => 'New-password-2026', 'password_confirmation' => 'New-password-2026',
        ])->assertRedirect('/admin/users');

        $activity = Activity::where('subject_type', 'user')->where('subject_id', $user->id)->sole();
        $this->assertSame('admin', $activity->changes['role']);
        $this->assertSame('••••••', $activity->changes['password']);
    }

    public function test_operators_cannot_complete_or_change_completed_reports(): void
    {
        $operator = User::factory()->operator()->create();
        $completed = RealEstate::factory()->create(['status' => ValuationStatus::Completed]);
        $open = RealEstate::factory()->create(['status' => ValuationStatus::InProgress]);

        $this->actingAs($operator)->get('/admin/real-estates/'.$completed->id.'/edit')->assertForbidden();
        $this->put('/admin/real-estates/'.$completed->id, ['name' => 'Forged', 'status' => 'completed'])->assertForbidden();

        $this->put('/admin/real-estates/'.$open->id, ['name' => $open->name, 'status' => 'completed'])
            ->assertSessionHasErrors('status');
        $this->assertSame(ValuationStatus::InProgress, $open->fresh()->status);
    }

    public function test_only_active_appraisers_can_be_assigned(): void
    {
        $appraiser = User::factory()->create();
        $operator = User::factory()->operator()->create();
        $object = RealEstate::factory()->create(['status' => ValuationStatus::New, 'appraiser_id' => null]);

        $this->actingAs($appraiser)->put('/admin/real-estates/'.$object->id, [
            'name' => $object->name, 'status' => 'new', 'appraiser_id' => $operator->id,
        ])->assertSessionHasErrors('appraiser_id');
    }

    public function test_invalid_rows_of_an_imported_price_list_are_skipped(): void
    {
        $user = User::factory()->create();
        $csv = "Nomi;Katalog raqami;O‘lchov;Narxi;Model\n"
            ."Old bamper;;pcs;1 850 000;\n"
            ."Minus;;pcs;-5;\n"
            .str_repeat('x', 300).";;pcs;100;\n";
        $file = UploadedFile::fake()->createWithContent('parts.csv', $csv);

        $this->actingAs($user)->post('/admin/spare-parts/import', ['file' => $file])->assertRedirect();

        $this->assertSame(['Old bamper'], SparePart::pluck('name')->all());
    }

    public function test_public_check_hides_most_of_the_plate_number(): void
    {
        $vehicle = Vehicle::factory()->create(['plate_number' => '01 A 123 BC', 'status' => ValuationStatus::Completed]);

        $this->get('/verify?code='.$vehicle->verify_code)->assertOk()
            ->assertSee('01 ••• BC')
            ->assertDontSee('01 A 123 BC');
    }

    public function test_telegram_link_uses_the_site_address_and_keeps_one_line_per_field(): void
    {
        config(['services.telegram.bot_token' => 'TOKEN', 'services.telegram.chat_id' => '123', 'app.url' => 'https://fairvalue.uz']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->withHeader('Host', 'evil.example')
            ->post('/request', ['name' => "Ali\nhttps://evil.example/login", 'phone' => '+998 91 111-22-33']);

        Http::assertSent(function ($request) {
            $lines = explode("\n", $request['text']);

            return str_contains($request['text'], 'https://fairvalue.uz/admin/leads/')
                && ! str_contains($request['text'], 'http://evil.example')
                && collect($lines)->contains('👤 Ali https://evil.example/login');
        });
    }
}
