<?php

namespace Tests\Feature\Admin;

use App\Models\Activity;
use App\Models\Client;
use App\Models\User;
use App\Services\Documents\DocumentAi;
use App\Services\Documents\DocumentData;
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
            ->assertSeeInOrder(['Karimov Anvar', 'Navoiy ko‘chasi, 5', 'Jismoniy shaxs', 'Saydaliyev Asliddin'])
            ->assertDontSee('Excel');
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
