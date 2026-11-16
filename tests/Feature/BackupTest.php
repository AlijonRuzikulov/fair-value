<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\File;
use PDO;
use Tests\TestCase;

class BackupTest extends TestCase
{
    // Without the wrapping transaction of RefreshDatabase, inside which SQLite cannot copy itself.
    use DatabaseMigrations;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('app/backups');
        File::deleteDirectory($this->dir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_the_database_is_copied_and_old_copies_are_removed(): void
    {
        Client::factory()->count(3)->create();

        $this->artisan('db:backup')->assertSuccessful();
        $copies = File::glob($this->dir.'/fairvalue-*.sqlite');
        $this->assertCount(1, $copies);
        $this->assertSame(3, (int) (new PDO('sqlite:'.$copies[0]))->query('select count(*) from clients')->fetchColumn());

        // Only the newest copies are kept.
        File::put($this->dir.'/fairvalue-2020-01-01-000000.sqlite', 'old');
        $this->artisan('db:backup', ['--keep' => 1])->assertSuccessful();
        $left = File::glob($this->dir.'/fairvalue-*.sqlite');
        $this->assertCount(1, $left);
        $this->assertStringNotContainsString('2020-01-01', $left[0]);
    }

    public function test_the_system_page_shows_the_last_copy(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->withSession(['locale' => 'uz'])->get('/admin/system')->assertOk()->assertSee('Bazaning zaxira nusxasi')->assertSee('hech qachon olinmagan');
        $this->artisan('db:backup')->assertSuccessful();
        $this->get('/admin/system')->assertOk()->assertDontSee('hech qachon olinmagan');
    }
}
