<?php

namespace Tests\Feature\Admin;

use App\Models\Activity;
use App\Models\Client;
use App\Models\Region;
use App\Models\User;
use App\Services\Documents\DocumentAi;
use App\Services\Documents\DocumentData;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The list of clients as on the valuation portal, and adding an individual
 * from the photo of the passport.
 */
class ClientListTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_list_has_the_columns_of_the_portal(): void
    {
        $user = User::factory()->create(['name' => 'Saydaliyev Asliddin']);
        $this->actingAs($user);
        Client::factory()->create(['type' => 'individual', 'last_name' => 'Karimov', 'first_name' => 'Anvar', 'address' => 'Navoiy ko‘chasi, 5']);

        $this->withSession(['locale' => 'uz'])->get('/admin/clients?type=individual')->assertOk()
            ->assertSee('<title>Jismoniy shaxslar ro‘yxati', false)
            ->assertSeeInOrder(['Qo‘shish', 'Qo‘shish yangi (pasport)', 'F.I.Sh', 'Manzili', 'Toifasi', 'Kiritilgan sana', 'O‘zgartirilgan sana', 'Tomonidan yaratilgan', 'Jarayon'])
            ->assertSeeInOrder(['Karimov Anvar', 'Navoiy ko‘chasi, 5', 'Jismoniy shaxs', 'A.SAYDALIYEV'])
            ->assertDontSee('Excel');

        // Legal entities: the head and the company, the e-mail; no passport button.
        Client::factory()->create(['type' => 'legal', 'company_name' => '"ALFA" MCHJ', 'director' => 'Xolmatov Rustam Ergashevich', 'email' => 'info@alfa.uz']);
        $this->withSession(['locale' => 'uz'])->get('/admin/clients?type=legal')->assertOk()
            ->assertSeeInOrder(['F.I.Sh', 'Manzili', 'E-mail', 'Toifasi', 'Xolmatov Rustam Ergashevich', '"ALFA" MCHJ', 'info@alfa.uz', 'Yuridik shaxs'], false)
            ->assertDontSee('Qo‘shish yangi (pasport)');
    }

    public function test_the_forms_are_the_portals(): void
    {
        $this->seed(RegionSeeder::class);
        $this->actingAs(User::factory()->create());
        $region = Region::whereHas('districts')->first();
        $district = $region->districts()->first();

        $this->withSession(['locale' => 'uz'])->get('/admin/clients/create?type=individual')->assertOk()
            ->assertSee('<title>Jismoniy shaxs ma’lumotlari', false)
            ->assertSeeInOrder(['Jismoniy shaxs ma’lumotlarini kiriting', 'Familiyasi', 'Ismi', 'Otasining ismi', 'Viloyati', 'Tumani', 'Manzili',
                'Telefon raqami', 'Pasport seriyasi va raqami', 'Jismoniy shaxsning shaxsiy identifikatsiya raqami (JShShIR 14 raqam)',
                'Pasport berilgan sana', 'Kim tomonidan berilgan', 'Bekor qilish', 'Saqlash'])
            ->assertSee('data-empty-alert="Telefon raqamini kiriting!"', false);

        $person = [
            'type' => 'individual', '_portal' => '1', 'last_name' => 'Karimov', 'first_name' => 'Anvar', 'middle_name' => 'Aliyevich',
            'region_id' => $region->id, 'district_id' => $district->id, 'address' => 'Navoiy 5', 'phone' => '+998 90 123 45 67',
            'passport' => 'ad 1234567', 'pinfl' => '30405901234567', 'passport_issued_at' => '2020-01-10', 'passport_issued_by' => 'IIV',
        ];
        $this->post('/admin/clients', $person)->assertRedirect();
        $client = Client::where('last_name', 'Karimov')->sole();
        $this->assertSame(['AD', '1234567'], [$client->passport_series, $client->passport_number]);
        $this->assertSame('1990-05-04', $client->birth_date->toDateString());  // from the PINFL
        $this->post('/admin/clients', [...$person, 'passport' => '12345', 'middle_name' => ''])->assertSessionHasErrors(['passport', 'middle_name']);

        $this->withSession(['locale' => 'uz'])->get('/admin/clients/create?type=legal')->assertOk()
            ->assertSeeInOrder(['Yuridik shaxs ma’lumotlarini kiriting', 'Tashkilot', 'Boshqaruv turi', 'Tashkilot nomi', 'Rahbarning familiyasi',
                'Rahbarning ismi', 'Rahbar otasining ismi', 'Lavozimi', 'Viloyati', 'Tumani', 'Tashkilot manzili', 'Telefon raqami', 'E-mail',
                'Hisob raqami', 'INN', 'MFO', 'Bank nomi', 'Yagona soliq to‘lovchi']);
        $this->post('/admin/clients', [
            'type' => 'legal', '_portal' => '1', 'organization' => 'Bank', 'management' => 'AJ', 'company_name' => '"ALFA" AJ',
            'director_last_name' => 'Xolmatov', 'director_first_name' => 'Rustam', 'director_middle_name' => 'Ergashevich', 'director_position' => 'Rais',
            'region_id' => $region->id, 'district_id' => $district->id, 'address' => 'Amir Temur 1', 'phone' => '+998 71 200 00 00', 'email' => 'info@alfa.uz',
            'bank_account' => '20208000900100200300', 'inn' => '123456789', 'bank_mfo' => '00450', 'bank_name' => 'Agrobank', 'single_tax' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $company = Client::where('company_name', '"ALFA" AJ')->sole();
        $this->assertSame(['Xolmatov Rustam Ergashevich', 'Rais', 'Bank', 'AJ', true], [$company->director, $company->director_position, $company->organization, $company->management, $company->single_tax]);
        $this->get('/admin/clients/'.$company->id.'/edit')->assertOk()->assertSee('name="director_middle_name" type="text" value="Ergashevich"', false);
    }

    public function test_a_new_individual_is_filled_in_from_the_passport_photo(): void
    {
        $this->actingAs(User::factory()->create());
        $sent = new \stdClass;
        $this->app->instance(DocumentAi::class, new class($sent) implements DocumentAi
        {
            public function __construct(private \stdClass $sent) {}

            public function available(): bool
            {
                return true;
            }

            public function read(array $files): array
            {
                $this->sent->files = $files;

                return DocumentData::normalise(['person' => [
                    'last_name' => 'KARIMOV', 'first_name' => 'ANVAR', 'middle_name' => 'ALIEVICH', 'birth_date' => '1990-05-04',
                    'pinfl' => '30405901234567', 'passport_series' => 'ad', 'passport_number' => '1234567',
                ]]);
            }
        });

        $this->post('/admin/clients/passport', ['files' => [UploadedFile::fake()->image('pasport.jpg', 2400, 1600)]])
            ->assertRedirect(route('admin.clients.create', ['type' => 'individual']))
            ->assertSessionHas('_old_input.last_name', 'Karimov')
            ->assertSessionHas('_old_input.passport_series', 'AD');
        $this->assertSame('image/jpeg', $sent->files[0]['mime']);
        $this->assertSame(1, Activity::where('event', 'passport_read')->count());

        // The form opens filled in; nothing is saved until the person saves it.
        $this->get(route('admin.clients.create', ['type' => 'individual']))->assertSee('value="Karimov"', false)->assertSee('value="30405901234567"', false);
        $this->assertSame(0, Client::count());

        $this->post('/admin/clients/passport', ['files' => [UploadedFile::fake()->create('notes.txt', 3, 'text/plain')]])->assertSessionHasErrors('files.0');
    }
}
