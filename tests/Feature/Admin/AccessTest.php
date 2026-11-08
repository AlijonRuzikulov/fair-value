<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Analogs\ClaudeListingAi;
use App\Services\Documents\ClaudeDocumentAi;
use App\Services\Documents\DocumentAi;
use App\Services\Documents\DocumentData;
use App\Support\Access;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The system needs no licence key: it always works. The people it is given
 * to may get a last day on their account, after which they can only look.
 * Reading documents with AI is open to everyone.
 */
class AccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RegionSeeder::class);
        $this->admin = User::factory()->admin()->create();
    }

    public function test_the_system_works_without_any_licence_key(): void
    {
        $this->actingAs($this->admin)->withSession(['locale' => 'uz'])->get('/admin/assets')->assertOk()
            ->assertDontSee('license-chip', false)->assertDontSee('license-note', false);
        $this->get('/admin/clients/create?type=individual')->assertOk();
        $this->post('/admin/clients', ['type' => 'individual', 'last_name' => 'Sinov', 'first_name' => 'Mijoz', 'phone' => '+998901234567'])->assertRedirect();
        $this->assertSame(1, Client::count());
        $this->get('/admin/settings')->assertOk()->assertDontSee('litsenziya kaliti');
        $this->put('/admin/settings/license', ['license_key' => 'FV1.x.y'])->assertNotFound();
    }

    public function test_everyone_reads_documents_with_ai(): void
    {
        // A person of an organisation, with no tick of any kind, as the administrator.
        config(['services.anthropic.key' => 'test-key']);
        $this->actingAs(User::factory()->create(['organization_id' => Organization::create(['name' => 'Xalq banki'])->id]));
        $this->assertTrue(app(ClaudeDocumentAi::class)->available());
        $this->assertTrue(app(ClaudeListingAi::class)->available());

        // Without the AI key the buttons say only what is missing.
        config(['services.anthropic.key' => null]);
        $missing = __('Reading documents needs an AI key (ANTHROPIC_API_KEY in .env).', [], 'uz');
        $this->flushSession();
        $this->actingAs($this->admin)->withSession(['locale' => 'uz']);
        $this->post('/admin/clients/passport', ['files' => [UploadedFile::fake()->image('pasport.jpg')]])->assertSessionHas('error', $missing);
        $car = Vehicle::factory()->create();
        $this->get('/admin/vehicles/'.$car->id)->assertOk()->assertSee('Texpasportdan to‘ldirish')->assertSee($missing)->assertDontSee('Premium');
        $this->post('/admin/vehicles/'.$car->id.'/tech-passport', ['files' => [UploadedFile::fake()->image('texpasport.jpg')]])
            ->assertSessionHas('error', $missing);
    }

    public function test_each_person_works_until_their_own_day(): void
    {
        $this->actingAs($this->admin)->withSession(['locale' => 'uz']);
        $xalq = Organization::create(['name' => 'Xalq banki']);
        $ishonch = Organization::create(['name' => 'Ishonch Baho MChJ']);

        // The administrator gives a bank employee access until a day, a valuer without an end.
        $this->get('/admin/users/create')->assertOk()
            ->assertSee('<title>Yangi foydalanuvchi', false)
            ->assertSeeInOrder(['Tashkilot', 'Kirish huquqi', 'Amal qilish muddati'])->assertDontSee('Premium');
        $form = ['email' => 'bank@xalqbank.uz', 'name' => 'Karimov Anvar', 'organization_id' => $xalq->id, 'role' => 'operator',
            'is_active' => '1', 'access_until' => now()->addDays(10)->toDateString(),
            'password' => 'Parol12345abc', 'password_confirmation' => 'Parol12345abc'];
        $this->post('/admin/users', $form)->assertRedirect('/admin/users');
        $this->post('/admin/users', ['email' => 'baho@ishonch.uz', 'name' => 'Toshmatov Ali', 'organization_id' => $ishonch->id, 'access_until' => ''] + $form)->assertRedirect('/admin/users');
        $bank = User::where('email', 'bank@xalqbank.uz')->sole();
        $valuer = User::where('email', 'baho@ishonch.uz')->sole();
        $this->assertNull($valuer->access_until);
        $this->get('/admin/users')->assertOk()->assertSee('Foydalanuvchilar')->assertDontSee('Premium')
            ->assertSeeInOrder(['Karimov Anvar', 'Xalq banki', now()->addDays(10)->format('d.m.Y')]);

        // Each sees their own day.
        // (A new session for each person: the panel signs out a session that belonged to someone else.)
        $this->flushSession();
        $this->actingAs($bank)->withSession(['locale' => 'uz'])->get('/admin/assets')->assertOk()
            ->assertSee(now()->addDays(10)->format('d.m.Y').' gacha')
            ->assertSee('Foydalanish muddatingiz tugashiga 10 kun qoldi');

        // After their day the person can only look; the others go on working.
        $bank->update(['access_until' => now()->subDay()->toDateString()]);
        $this->flushSession();
        $this->actingAs($bank->refresh())->withSession(['locale' => 'uz'])->get('/admin/assets')->assertOk()->assertSee('Sizning foydalanish muddatingiz');
        $this->from('/admin/assets')->post('/admin/assets', ['name' => 'Stanok'])->assertRedirect('/admin/assets')->assertSessionHas('error');
        $this->assertTrue(Access::for($valuer)->allowsChanges());
        $this->assertTrue(Access::for($this->admin)->allowsChanges());
    }

    public function test_a_vehicle_is_filled_in_from_its_registration_certificate(): void
    {
        Storage::fake('local');
        $car = Vehicle::factory()->create(['brand' => null, 'model' => null, 'year_made' => null]);
        $this->app->instance(DocumentAi::class, new class implements DocumentAi
        {
            public function available(): bool
            {
                return true;
            }

            public function read(array $files): array
            {
                return DocumentData::normalise(['vehicle' => [
                    'brand' => 'CHEVROLET', 'model' => 'Cobalt', 'year_made' => 2023, 'color' => 'Oq', 'plate_number' => '01 A 123 BC',
                    'vin' => 'xwb 4a11cd1234567', 'engine_number' => 'L2C 123456', 'chassis_number' => null, 'body_type' => 'Sedan',
                    'engine_volume' => 1485, 'fuel_type' => 'Benzin', 'tech_passport' => 'AAF 1234567', 'owner_name' => 'KARIMOV ANVAR',
                ]]);
            }
        });

        $this->actingAs($this->admin)->withSession(['locale' => 'uz'])
            ->post('/admin/vehicles/'.$car->id.'/tech-passport', ['files' => [UploadedFile::fake()->image('texpasport.jpg')]])
            ->assertRedirect(route('admin.vehicles.show', $car->id).'#object-data')
            ->assertSessionHas('status')
            ->assertSessionHas('_old_input.brand', 'Chevrolet')
            ->assertSessionHas('_old_input.plate_number', '01A123BC')
            ->assertSessionHas('_old_input.vin', 'XWB4A11CD1234567')
            ->assertSessionHas('_old_input.engine_volume', 1.5)
            ->assertSessionHas('_old_input.fuel_type', 'petrol');

        // Nothing is saved until the appraiser checks the fields; the photo is kept with the documents.
        $this->assertNull($car->refresh()->brand);
        $this->assertSame(['technical_passport'], $car->attachments()->pluck('kind')->all());
        $this->get('/admin/vehicles/'.$car->id)->assertOk()
            ->assertSee('name="brand" type="text" value="Chevrolet"', false)
            ->assertSee('name="tech_passport" type="text" value="AAF 1234567"', false);
    }
}
