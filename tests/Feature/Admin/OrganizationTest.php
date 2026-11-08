<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\Attachment;
use App\Models\Client;
use App\Models\Organization;
use App\Models\RealEstate;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Access;
use App\Support\DocumentNumber;
use App\Support\Tenant;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Each organisation the system is given to works in its own workspace: the
 * others, and the company that runs the system, do not see its work. Its
 * contracts and reports carry its own details. A passport, a cadastral
 * number or a car typed again shows only who valued it before and when.
 */
class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $bank;

    private User $banker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RegionSeeder::class);
        $this->admin = User::factory()->admin()->create();
        $this->bank = Organization::create(['name' => 'Xalq banki', 'settings' => ['legal_name' => '"Xalq banki" ATB', 'inn' => '201234567']]);
        $this->banker = User::factory()->create(['role' => UserRole::Appraiser, 'organization_id' => $this->bank->id]);
    }

    /** Signs in as another person (a new session: the panel signs out a session that was someone else's). */
    private function as(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user)->withSession(['locale' => 'uz']);
    }

    public function test_the_work_of_an_organisation_is_seen_only_in_its_workspace(): void
    {
        Storage::fake('local');
        $ours = Client::factory()->create(['last_name' => 'Boshliqov']);
        $ourHouse = RealEstate::factory()->create();
        $ourFile = Attachment::keep($ours, UploadedFile::fake()->create('pasport.pdf', 10, 'application/pdf'), null, $this->admin->id);

        // The bank adds a client: it is kept in the bank's workspace.
        $this->as($this->banker)->post('/admin/clients', ['type' => 'individual', 'last_name' => 'Bankov', 'first_name' => 'Mijoz', 'phone' => '+998901234567'])->assertRedirect();
        $theirs = Client::withoutGlobalScopes()->where('last_name', 'Bankov')->sole();
        $this->assertSame($this->bank->id, $theirs->organization_id);

        $this->get('/admin/clients?type=individual')->assertOk()->assertSee('Bankov')->assertDontSee('Boshliqov');
        $this->get('/admin/clients/'.$ours->id)->assertNotFound();
        $this->get('/admin/real-estates/'.$ourHouse->id)->assertNotFound();
        $this->get('/admin/attachments/'.$ourFile->id)->assertNotFound();
        $this->post('/admin/real-estates', ['owner_id' => $ours->id])->assertSessionHasErrors('owner_id');

        // What belongs to the company that runs the system is not theirs to manage.
        foreach (['/admin/users', '/admin/organizations', '/admin/settings', '/admin/activity', '/admin/leads'] as $page) {
            $this->get($page)->assertForbidden();
        }
        $this->get('/admin/clients?type=individual')->assertDontSee(route('admin.leads.index'))->assertDontSee(__('Management', [], 'uz'));

        // Nor does the company that runs the system see the bank's work, in the lists or in the audit log.
        $this->as($this->admin)->get('/admin/clients?type=individual')->assertOk()->assertSee('Boshliqov')->assertDontSee('Bankov');
        $this->get('/admin/clients/'.$theirs->id)->assertNotFound();
        $this->assertTrue(Activity::where('subject_type', 'client')->where('subject_id', $theirs->id)->where('organization_id', $this->bank->id)->exists());
        $this->get('/admin/activity')->assertOk()->assertDontSee('Bankov');

        // Someone whose organisation is gone is let in nowhere, not into the main workspace.
        $this->banker->forceFill(['organization_id' => 999])->save();
        $this->as($this->banker->refresh())->get('/admin/clients?type=individual')->assertForbidden();
    }

    public function test_an_organisation_prints_its_own_details_and_numbers(): void
    {
        RealEstate::factory()->create(['contract_number' => '7-'.now()->format('y')]);
        Setting::put(['legal_name' => '"FAIR VALUE" MChJ', 'phone' => '+998 90 159-01-02']);

        Tenant::set($this->bank);
        $this->assertSame('"Xalq banki" ATB', Setting::get('legal_name'));
        $this->assertSame('Xalq banki', Setting::get('name'));
        $this->assertNull(Setting::get('phone'), 'the details of the company that runs the system are not printed for the bank');
        $this->assertSame(config('fairvalue.company.contract_number_format'), Setting::get('contract_number_format'));
        $this->assertSame('1-'.now()->format('y'), DocumentNumber::next('contract'), 'each workspace numbers its own contracts');
        Tenant::set(null);
        $this->assertSame('"FAIR VALUE" MChJ', Setting::get('legal_name'));
        $this->assertSame('8-'.now()->format('y'), DocumentNumber::next('contract'));
        Tenant::clear();

        // A report of the bank is checked on the website under the bank's name.
        $report = RealEstate::factory()->create(['organization_id' => $this->bank->id, 'status' => 'completed', 'market_value' => 100]);
        $this->withSession(['locale' => 'uz'])->get('/verify?code='.$report->verify_code)->assertOk()->assertSee('"Xalq banki" ATB');
    }

    public function test_the_main_administrator_keeps_the_organisations_and_their_people(): void
    {
        $this->as($this->admin)->get('/admin/organizations')->assertOk()->assertSee('Xalq banki')->assertSee('201234567');
        $this->post('/admin/organizations', ['name' => 'Ishonch Baho', 'legal_name' => '"Ishonch Baho" MChJ', 'director' => 'Aliyev A.', 'vat_rate' => '12', 'report_number_format' => 'IB-{nnnn}'])
            ->assertRedirect('/admin/organizations');
        $valuer = Organization::where('name', 'Ishonch Baho')->sole();
        $this->assertSame(['legal_name' => '"Ishonch Baho" MChJ', 'director' => 'Aliyev A.', 'vat_rate' => '12', 'report_number_format' => 'IB-{nnnn}'], $valuer->settings);
        $this->post('/admin/organizations', ['name' => 'Xato', 'report_number_format' => 'IB'])->assertSessionHasErrors('report_number_format');

        // People are put into an organisation on their account.
        $this->get('/admin/users/create')->assertOk()->assertSee('Ishonch Baho');
        $this->post('/admin/users', ['name' => 'Valiyev Vali', 'email' => 'vali@ishonch.uz', 'role' => 'admin', 'organization_id' => $valuer->id,
            'is_active' => '1', 'password' => 'Parol12345abc', 'password_confirmation' => 'Parol12345abc'])->assertRedirect('/admin/users');
        $director = User::where('email', 'vali@ishonch.uz')->sole();
        $this->assertSame($valuer->id, $director->organization_id);

        // The director of an organisation works in its workspace, without the rights of the main administrator.
        $this->assertFalse($director->can('admin'));
        $this->assertTrue(Access::for($director)->allowsChanges());

        // An organisation with people is not deleted; an empty one is.
        $this->as($this->admin)->delete('/admin/organizations/'.$valuer->id)->assertSessionHas('error');
        $empty = Organization::create(['name' => "Bo'sh"]);
        $this->delete('/admin/organizations/'.$empty->id)->assertRedirect('/admin/organizations');
        $this->assertModelMissing($empty);
    }

    public function test_a_passport_or_a_cadastral_number_shows_who_valued_it_before_and_when(): void
    {
        // Three years ago the bank valued a house of this person.
        $client = Client::factory()->create(['organization_id' => $this->bank->id, 'passport_series' => 'AA', 'passport_number' => '1234567', 'pinfl' => '31234567890123']);
        $house = RealEstate::factory()->create(['organization_id' => $this->bank->id, 'cadastral_number' => '10:01:05:01:02:0123', 'valuation_date' => now()->subYears(3)->toDateString()]);
        $house->clients()->attach($client->id, ['role' => 'owner']);
        Vehicle::factory()->create(['organization_id' => $this->bank->id, 'vin' => 'XWB3L32EDCA123456', 'plate_number' => '01A123BC', 'valuation_date' => '2024-05-02']);
        $when = now()->subYears(3)->format('d.m.Y');

        // Now the person comes to the company that runs the system.
        $this->as($this->admin);
        $expected = [['organization' => 'Xalq banki', 'date' => $when, 'kind' => 'Ko‘chmas mulk']];
        $this->getJson('/admin/history?field=passport&value=aa 1234567')->assertOk()->assertExactJson(['items' => $expected]);
        $this->getJson('/admin/history?field=pinfl&value=31234567890123')->assertExactJson(['items' => $expected]);
        $this->getJson('/admin/history?field=cadastral&value='.urlencode('10 01 05 01 02 0123'))->assertExactJson(['items' => $expected]);
        $this->getJson('/admin/history?field=vin&value=xwb3l32edca123456')->assertJsonPath('items.0.date', '02.05.2024');
        $this->getJson('/admin/history?field=plate&value='.urlencode('01 A 123 BC'))->assertJsonPath('items.0.organization', 'Xalq banki');
        // Not enough of a number, or one nobody has: nothing.
        $this->getJson('/admin/history?field=passport&value=AA12')->assertExactJson(['items' => []]);
        $this->getJson('/admin/history?field=cadastral&value=10:01:05:01:02:9999')->assertExactJson(['items' => []]);
        $this->getJson('/admin/history?field=email&value=a@b.uz')->assertUnprocessable();

        // The forms ask while the number is typed.
        $this->get('/admin/clients/create?type=individual')->assertOk()->assertSee('data-history="passport"', false)->assertSee('data-history="pinfl"', false);
        $this->get('/admin/real-estates/create?kind=residential')->assertOk()->assertSee('data-history="cadastral"', false);

        // The bank's own records are in its own lists, not in the history; the person's later valuation elsewhere is.
        Client::factory()->create(['passport_series' => 'AA', 'passport_number' => '1234567']);
        $this->as($this->banker)->getJson('/admin/history?field=passport&value=AA1234567')
            ->assertExactJson(['items' => [['organization' => Setting::main('name'), 'date' => now()->format('d.m.Y'), 'kind' => 'Mijoz']]]);
    }
}
