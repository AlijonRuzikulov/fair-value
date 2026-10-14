<?php

namespace Tests\Feature\Audit;

use App\Models\Activity;
use App\Models\CarModel;
use App\Models\Client;
use App\Models\RealEstate;
use App\Models\User;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Signing in and out, data entered and data taken out are written to the audit log.
 */
class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private function last(string $event): ?Activity
    {
        return Activity::where('event', $event)->latest('id')->first();
    }

    public function test_sign_in_failure_and_sign_out_are_logged_with_the_device(): void
    {
        $user = User::factory()->create(['email' => 'a@fairvalue.uz', 'password' => 'secret-password-1']);
        $browser = ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/130.0 Safari/537.36'];

        $this->withHeaders($browser)->post('/login', ['email' => 'A@fairvalue.uz', 'password' => 'wrong-one'])->assertSessionHasErrors('email');
        $failed = $this->last('login_failed');
        $this->assertSame('auth', $failed->category);
        $this->assertSame('a@fairvalue.uz', $failed->user_email);
        $this->assertNull($failed->user_id, 'The person who failed is not assumed to be the account owner.');
        $this->assertSame($user->id, $failed->subject_id);
        $this->assertSame('wrong_password', $failed->changes['reason']);
        $this->assertStringNotContainsString('wrong-one', json_encode($failed->getAttributes()), 'Passwords are never logged.');

        $this->post('/login', ['email' => 'nobody@fairvalue.uz', 'password' => 'x'])->assertSessionHasErrors('email');
        $this->assertSame('unknown_email', $this->last('login_failed')->changes['reason']);

        $this->withHeaders($browser)->post('/login', ['email' => 'a@fairvalue.uz', 'password' => 'secret-password-1'])->assertRedirect('/admin');
        $login = $this->last('login');
        $this->assertSame($user->id, $login->user_id);
        $this->assertSame('POST /login', $login->url);
        $this->assertSame('127.0.0.1', $login->ip_address);
        $this->assertSame('Chrome · Windows', $login->deviceLabel());

        $this->post('/logout')->assertRedirect('/login');
        $this->assertSame($user->id, $this->last('logout')->user_id);
    }

    public function test_repeated_wrong_passwords_block_sign_in_and_raise_an_alert(): void
    {
        config(['services.telegram.bot_token' => 'TOKEN', 'services.telegram.chat_id' => '1']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        User::factory()->create(['email' => 'a@fairvalue.uz']);

        for ($i = 0; $i < 7; $i++) {
            $this->post('/login', ['email' => 'a@fairvalue.uz', 'password' => 'guess'.$i]);
        }

        $this->assertSame(1, Activity::where('event', 'login_locked')->count(), 'Logged once per minute, not on every blocked try.');
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request['text'], 'a@fairvalue.uz'));
    }

    public function test_deactivation_and_password_changes_end_sessions_and_are_logged(): void
    {
        $user = User::factory()->create(['password' => 'secret-password-1']);
        $this->actingAs($user)->get('/admin')->assertOk();

        $user->update(['is_active' => false]);
        $this->get('/admin')->assertRedirect('/login');
        $this->assertSame('deactivated', $this->last('session_ended')->changes['reason']);

        $user->update(['is_active' => true]);
        $this->actingAs($user)->put('/admin/profile', [
            'name' => 'New name', 'email' => $user->email,
            'current_password' => 'secret-password-1', 'password' => 'Another-pass-2026', 'password_confirmation' => 'Another-pass-2026',
        ])->assertRedirect();
        $this->assertNotNull($this->last('password_changed'));
        $this->assertSame('New name', $this->last('updated')->changes['name']['new']);
    }

    public function test_data_taken_out_is_logged(): void
    {
        $this->seed(RegionSeeder::class);
        $user = User::factory()->create();
        $flat = RealEstate::factory()->residential()->create();
        $client = Client::factory()->create();
        $this->actingAs($user);

        $this->get('/admin/real-estates/'.$flat->id)->assertOk();
        $this->get('/admin/real-estates/'.$flat->id)->assertOk();
        $this->assertSame(1, Activity::where('event', 'viewed')->where('subject_id', $flat->id)->count(), 'One record per half hour, not per page load.');

        $this->get('/admin/clients/'.$client->id)->assertOk();
        $this->assertSame('client', $this->last('viewed')->subject_type);

        $this->get('/admin/real-estates/'.$flat->id.'/print')->assertOk();
        $this->assertSame($flat->id, $this->last('printed')->subject_id);

        $this->get('/admin/clients/export?type=individual')->assertOk();
        $export = $this->last('exported');
        $this->assertSame('access', $export->category);
        $this->assertSame(1, $export->changes['rows']);
        $this->assertSame('individual', $export->changes['filters']['type']);

        Storage::fake('local');
        $this->post('/admin/attachments', ['type' => 'client', 'id' => $client->id, 'files' => [UploadedFile::fake()->create('pasport.pdf', 10, 'application/pdf')]]);
        $this->assertSame(['pasport.pdf'], $this->last('uploaded')->changes['files']);
        $attachment = $client->attachments()->sole();
        $this->get('/admin/attachments/'.$attachment->id)->assertOk();
        $this->assertSame('pasport.pdf', $this->last('downloaded')->changes['file']);
    }

    public function test_public_report_checks_are_logged(): void
    {
        $this->seed(RegionSeeder::class);
        $flat = RealEstate::factory()->create();

        $this->get('/verify?code='.$flat->verify_code)->assertOk();
        $this->get('/verify?code='.$flat->verify_code)->assertOk();
        $this->get('/verify?code=FV-ZZZZ-ZZZZ')->assertOk();

        $checks = Activity::where('event', 'verified')->orderBy('id')->get();
        $this->assertCount(2, $checks);
        $this->assertTrue($checks[0]->changes['found']);
        $this->assertFalse($checks[1]->changes['found']);
        $this->assertSame(__('Website visitor'), $checks[0]->actorLabel());
    }

    public function test_settings_changes_keep_old_and_new_values(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put('/admin/settings', ['name' => 'Fair Value', 'phone' => '+998 90 000-00-00'])->assertRedirect();

        $settings = $this->last('settings');
        $this->assertSame('+998 90 159-01-02', $settings->changes['phone']['old']);
        $this->assertSame('+998 90 000-00-00', $settings->changes['phone']['new']);
        $this->assertArrayNotHasKey('name', $settings->changes, 'Unchanged values are not listed.');
    }

    public function test_an_import_is_one_record_not_one_per_row(): void
    {
        $user = User::factory()->create();
        $csv = "Marka;Model;Toifa;Kuzov;Yillar;Narx\nChevrolet;Cobalt;;;;\nChevrolet;Nexia;;;;\nChevrolet;Spark;;;;\n";

        $this->actingAs($user)->post('/admin/car-models/import', ['file' => UploadedFile::fake()->createWithContent('models.csv', $csv)])->assertRedirect();

        $this->assertSame(3, CarModel::count());
        $this->assertSame(0, Activity::where('event', 'created')->where('subject_type', 'car_model')->count());
        $import = $this->last('imported');
        $this->assertSame(3, $import->changes['rows']);
        $this->assertSame('models.csv', $import->changes['file']);
    }

    public function test_the_audit_log_and_status_pages_are_for_administrators(): void
    {
        $admin = User::factory()->admin()->create();
        Activity::record('login', $admin, null, $admin->name, $admin);

        $this->actingAs(User::factory()->create())->get('/admin/activity')->assertForbidden();
        $this->get('/admin/system')->assertForbidden();

        $this->actingAs($admin)->get('/admin/activity?category=auth')->assertOk()->assertSee($admin->name);
        $this->get('/admin/system')->assertOk();
        $this->get('/admin/activity/export')->assertOk();
        $this->assertSame('audit-'.now()->format('Y-m-d').'.csv', $this->last('exported')->changes['file'], 'Exporting the log is itself logged.');
    }
}
