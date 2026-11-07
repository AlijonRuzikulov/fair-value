<?php

namespace Tests\Feature\Admin;

use App\Enums\ValuationStatus;
use App\Models\Activity;
use App\Models\Asset;
use App\Models\Client;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Analogs\ClaudeListingAi;
use App\Services\Documents\ClaudeDocumentAi;
use App\Services\Documents\DocumentAi;
use App\Services\Documents\DocumentData;
use App\Support\Access;
use App\Support\License;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Every company's copy works on a licence key from the seller: its plan and
 * end are always in sight, after the end the panel is for viewing only, and
 * reading documents with AI is in the Premium plan.
 */
class LicenseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RegionSeeder::class);
        $this->admin = User::factory()->admin()->create();
    }

    public function test_the_plan_and_its_end_are_in_sight(): void
    {
        $this->license(License::PREMIUM, now()->addDays(200)->toDateString());
        $this->actingAs($this->admin)->withSession(['locale' => 'uz'])->get('/admin/assets')->assertOk()
            ->assertSee('license-chip is-premium', false)->assertSee('Premium')->assertSee(now()->addDays(200)->format('d.m.Y').' gacha')
            ->assertDontSee('license-note', false);

        // A month before the end a warning appears.
        $this->license(License::STANDARD, now()->addDays(12)->toDateString());
        $this->get('/admin/assets')->assertOk()
            ->assertSee('license-chip is-soon', false)->assertSee('Oddiy')
            ->assertSee('Litsenziya muddati tugashiga 12 kun qoldi');
        $this->get('/admin/settings')->assertOk()
            ->assertSeeInOrder(['Tarif va litsenziya', 'Kompaniya', 'Sinov MChJ', 'Tarif', 'Oddiy', 'Amal qilish muddati', 'Holati', '12 kun qoldi', 'Yangi litsenziya kaliti']);
    }

    public function test_after_the_end_the_panel_is_for_viewing_only(): void
    {
        $asset = Asset::factory()->create(['name' => 'Stanok', 'status' => ValuationStatus::New]);
        $this->license(License::PREMIUM, now()->subDay()->toDateString());
        $this->actingAs($this->admin)->withSession(['locale' => 'uz']);

        // Everything can be seen and printed.
        $this->get('/admin/assets')->assertOk()->assertSee('Stanok')
            ->assertSee('license-chip is-off', false)->assertSee('Litsenziya muddati '.now()->subDay()->format('d.m.Y').' da tugagan');
        $this->get('/admin/assets/'.$asset->id)->assertOk();
        $this->get('/admin/assets/'.$asset->id.'/contract')->assertOk();

        // Nothing can be added or changed, and the forms do not open.
        $this->from('/admin/assets')->post('/admin/assets', ['name' => 'Yangi'])->assertRedirect('/admin/assets')->assertSessionHas('error');
        $this->from('/admin/assets/'.$asset->id)->put('/admin/assets/'.$asset->id, ['name' => 'O‘zgardi'])->assertRedirect('/admin/assets/'.$asset->id);
        $this->from('/admin/clients?type=individual')->get('/admin/clients/create?type=individual')->assertRedirect('/admin/clients?type=individual');
        $this->delete('/admin/assets/'.$asset->id)->assertRedirect();
        $this->assertSame('Stanok', $asset->refresh()->name);
        $this->assertSame(1, Asset::count());
        $this->assertSame(0, Client::count());
        $this->postJson('/admin/assets', [])->assertForbidden();

        // A new key from the seller opens it again.
        $key = $this->license(License::PREMIUM, now()->addYear()->toDateString(), 'Baholash Servis MChJ');
        $this->license(License::PREMIUM, now()->subDay()->toDateString());
        $this->put('/admin/settings/license', ['license_key' => $key])->assertRedirect()->assertSessionHas('status');
        $this->get('/admin/settings')->assertSee('Baholash Servis MChJ')->assertDontSee('license-note', false);
        $this->assertTrue(License::current()->allowsChanges());
        $this->assertSame('Baholash Servis MChJ', Activity::where('event', 'license')->sole()->changes['company']);
    }

    public function test_without_a_key_or_with_a_changed_one_nothing_can_be_changed(): void
    {
        $this->license(null);
        $this->actingAs($this->admin)->withSession(['locale' => 'uz'])->get('/admin/assets')->assertOk()
            ->assertSee('Litsenziya yo‘q')->assertSee('Amaldagi litsenziya kaliti yo‘q');
        $this->post('/admin/assets', ['name' => 'Yangi'])->assertSessionHas('error');

        // A key with a later date written into it is refused: its signature no longer matches.
        $key = $this->license(License::STANDARD, now()->addMonth()->toDateString());
        [$prefix, $payload, $signature] = explode('.', $key);
        $data = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        $data['u'] = '2099-12-31';
        $data['p'] = License::PREMIUM;
        $forged = $prefix.'.'.rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=').'.'.$signature;
        $this->assertNull(License::verify($forged));
        $this->assertNotNull(License::verify($key));

        $this->license(null);
        $this->put('/admin/settings/license', ['license_key' => $forged])->assertSessionHasErrors('license_key');
        $this->assertFalse(License::current()->allowsChanges());
    }

    public function test_the_seller_issues_keys_and_companies_cannot(): void
    {
        $this->actingAs($this->admin)->withSession(['locale' => 'uz']);
        $this->post('/admin/settings/license/issue', ['company' => 'X', 'plan' => 'premium', 'until' => now()->addYear()->toDateString()])->assertForbidden();

        $this->sellerCopy();
        $this->get('/admin/assets')->assertOk()->assertDontSee('license-chip', false);
        $this->get('/admin/settings')->assertOk()->assertSee('Sotuvchining nusxasi')->assertSee('Kompaniya uchun kalit');
        $until = now()->addMonths(6)->toDateString();
        $this->post('/admin/settings/license/issue', ['company' => 'Ishonch Baho MChJ', 'plan' => 'standard', 'until' => $until])
            ->assertRedirect()->assertSessionHas('issued_license');

        $payload = License::verify(session('issued_license'));
        $this->assertSame(['Ishonch Baho MChJ', 'standard', $until], [$payload['c'], $payload['p'], $payload['u']]);
        $this->get('/admin/settings')->assertSee(session('issued_license'));
    }

    public function test_reading_documents_with_ai_is_in_the_premium_plan(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $this->actingAs($this->admin)->withSession(['locale' => 'uz']);
        $this->assertTrue(app(ClaudeDocumentAi::class)->available());

        $this->license(License::STANDARD, now()->addYear()->toDateString());
        $this->assertFalse(app(ClaudeDocumentAi::class)->available());
        $this->assertFalse(app(ClaudeListingAi::class)->available());
        $this->post('/admin/clients/passport', ['files' => [UploadedFile::fake()->image('pasport.jpg')]])
            ->assertSessionHas('error', 'Bu imkoniyat Premium tarifda.');
        $this->get('/admin/clients?type=individual')->assertOk()->assertSee('Bu imkoniyat Premium tarifda.');

        $car = Vehicle::factory()->create();
        $this->get('/admin/vehicles/'.$car->id)->assertOk()->assertSee('Texpasportdan to‘ldirish')->assertSee('Bu imkoniyat Premium tarifda.');
        $this->post('/admin/vehicles/'.$car->id.'/tech-passport', ['files' => [UploadedFile::fake()->image('texpasport.jpg')]])
            ->assertSessionHas('error', 'Bu imkoniyat Premium tarifda.');
    }

    public function test_each_person_gets_premium_by_a_tick_and_works_until_their_own_day(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $this->actingAs($this->admin)->withSession(['locale' => 'uz']);

        // The administrator gives a bank employee Standard until a day, a valuer Premium.
        $this->get('/admin/users/create')->assertOk()
            ->assertSee('<title>Yangi foydalanuvchi', false)
            ->assertSeeInOrder(['Tashkilot', 'Kirish huquqi', 'Premium', 'Amal qilish muddati']);
        $form = ['email' => 'bank@xalqbank.uz', 'name' => 'Karimov Anvar', 'organization' => 'Xalq banki', 'role' => 'operator',
            'is_active' => '1', 'premium' => '0', 'access_until' => now()->addDays(10)->toDateString(),
            'password' => 'Parol12345abc', 'password_confirmation' => 'Parol12345abc'];
        $this->post('/admin/users', $form)->assertRedirect('/admin/users');
        $this->post('/admin/users', ['email' => 'baho@ishonch.uz', 'name' => 'Toshmatov Ali', 'organization' => 'Ishonch Baho MChJ', 'premium' => '1'] + $form)->assertRedirect('/admin/users');
        $bank = User::where('email', 'bank@xalqbank.uz')->sole();
        $valuer = User::where('email', 'baho@ishonch.uz')->sole();
        $this->assertSame([false, true], [$bank->premium, $valuer->premium]);
        $this->get('/admin/users')->assertOk()->assertSee('Foydalanuvchilar')
            ->assertSeeInOrder(['Karimov Anvar', 'Xalq banki', 'Oddiy', now()->addDays(10)->format('d.m.Y')]);

        // Each sees their own plan and day; only the ticked one reads documents with AI.
        // (A new session for each person: the panel signs out a session that belonged to someone else.)
        $this->flushSession();
        $this->actingAs($bank)->withSession(['locale' => 'uz'])->get('/admin/assets')->assertOk()
            ->assertSee('Oddiy')->assertSee(now()->addDays(10)->format('d.m.Y').' gacha')
            ->assertSee('Foydalanish muddatingiz tugashiga 10 kun qoldi');
        $this->assertFalse(app(ClaudeDocumentAi::class)->available());
        $this->actingAs($valuer);
        $this->assertTrue(app(ClaudeDocumentAi::class)->available());

        // After their day the person can only look; the others go on working.
        $bank->update(['access_until' => now()->subDay()->toDateString()]);
        $this->flushSession();
        $this->actingAs($bank->refresh())->withSession(['locale' => 'uz'])->get('/admin/assets')->assertOk()->assertSee('Sizning foydalanish muddatingiz');
        $this->from('/admin/assets')->post('/admin/assets', ['name' => 'Stanok'])->assertRedirect('/admin/assets')->assertSessionHas('error');
        $this->assertTrue(Access::for($valuer)->allowsChanges());

        // The plan of the copy still limits everybody.
        $this->license(License::STANDARD, now()->addYear()->toDateString());
        $this->assertFalse(Access::for($valuer)->isPremium());
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
