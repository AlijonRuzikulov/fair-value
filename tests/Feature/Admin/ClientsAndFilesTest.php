<?php

namespace Tests\Feature\Admin;

use App\Models\Attachment;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientsAndFilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_individual_and_legal_clients_are_validated_by_type(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/admin/clients', ['type' => 'individual', 'pinfl' => '123'])
            ->assertSessionHasErrors(['last_name', 'first_name', 'pinfl']);

        $this->post('/admin/clients', ['type' => 'legal', 'company_name' => '"Test" MChJ', 'inn' => '301 234 567'])
            ->assertRedirect();

        $client = Client::sole();
        $this->assertTrue($client->isLegal());
        $this->assertSame('301234567', $client->inn);
    }

    public function test_client_search_finds_by_passport_and_name(): void
    {
        $user = User::factory()->create();
        Client::factory()->create(['last_name' => 'Karimov', 'first_name' => 'Aziz', 'passport_series' => 'AB', 'passport_number' => '1234567']);
        Client::factory()->create(['last_name' => 'Boshqa']);

        $byPassport = $this->actingAs($user)->getJson('/admin/clients/search?q=AB 1234567')->json();
        $this->assertCount(1, $byPassport);
        $this->assertStringStartsWith('Karimov Aziz', $byPassport[0]['title']);

        $this->assertCount(1, $this->getJson('/admin/clients/search?q=Karim')->json());
    }

    public function test_files_are_attached_downloaded_and_deleted(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($user)->post('/admin/attachments', [
            'type' => 'client',
            'id' => $client->id,
            'files' => [UploadedFile::fake()->create('pasport.pdf', 120, 'application/pdf')],
        ])->assertRedirect();

        $file = Attachment::sole();
        $this->assertSame('pasport.pdf', $file->name);
        Storage::disk('local')->assertExists($file->path);

        $this->get('/admin/attachments/'.$file->id.'?download=1')->assertOk()->assertDownload('pasport.pdf');

        $this->delete('/admin/attachments/'.$file->id)->assertRedirect();
        Storage::disk('local')->assertMissing($file->path);
    }

    public function test_dangerous_files_are_rejected(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($user)->post('/admin/attachments', [
            'type' => 'client',
            'id' => $client->id,
            'files' => [UploadedFile::fake()->create('shell.php', 1, 'application/x-php')],
        ])->assertSessionHasErrors('files.0');

        $this->assertSame(0, Attachment::count());
    }
}
