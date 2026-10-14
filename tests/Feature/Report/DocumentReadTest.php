<?php

namespace Tests\Feature\Report;

use App\Enums\ValuationStatus;
use App\Models\Activity;
use App\Models\Client;
use App\Models\RealEstate;
use App\Models\User;
use App\Services\Documents\DocumentAi;
use App\Services\Documents\DocumentData;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Filling the owner and the object from scans of the passport and the
 * cadastral extract.
 */
class DocumentReadTest extends TestCase
{
    use RefreshDatabase;

    private User $appraiser;

    private RealEstate $flat;

    /** @var list<array{name: string, mime: string, data: string}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(RegionSeeder::class);
        $this->appraiser = User::factory()->create();
        $this->flat = RealEstate::factory()->residential()->create([
            'cadastral_number' => null, 'total_area' => 50, 'living_area' => null, 'year_built' => null, 'address' => null, 'region_id' => null, 'district_id' => null,
            'status' => ValuationStatus::InProgress, 'report' => null,
        ]);
    }

    private function fakeAi(array $answer): void
    {
        $test = $this;
        $this->app->instance(DocumentAi::class, new class($answer, $test) implements DocumentAi
        {
            public function __construct(private array $answer, private DocumentReadTest $test) {}

            public function available(): bool
            {
                return true;
            }

            public function read(array $files): array
            {
                $this->test->received($files);

                return DocumentData::normalise($this->answer);
            }
        });
    }

    public function received(array $files): void
    {
        $this->sent = $files;
    }

    private function upload(string $name): int
    {
        $this->actingAs($this->appraiser)->post('/admin/attachments', [
            'type' => 'real_estate', 'id' => $this->flat->id, 'files' => [UploadedFile::fake()->image($name, 2600, 1800)],
        ])->assertRedirect();

        return $this->flat->attachments()->latest('id')->value('id');
    }

    private function answer(): array
    {
        return [
            'documents' => [['file' => 0, 'kind' => 'id_card'], ['file' => 1, 'kind' => 'cadastral_extract']],
            'person' => [
                'last_name' => 'XODJAYEVA', 'first_name' => 'XANAY', 'middle_name' => 'MAVLYANKULOVNA', 'birth_date' => '1953-08-08',
                'pinfl' => '40808530470016', 'passport_series' => 'ae', 'passport_number' => '3843163', 'passport_issued_by' => 'IIV 27419',
                'passport_issued_at' => '2025-08-19', 'address' => null,
            ],
            'property' => [
                'cadastral_number' => '11:20:01:08:01:0079:0001:051', 'object_type' => 'Turar', 'name' => "Ko'p qavatli uydagi xonadon",
                'address' => 'Toshkent viloyati, Chirchiq shahri, Turon MFY, 4 kichik nohiya, 44-uy, 51-xonadon',
                'region' => 'Toshkent viloyati', 'district' => 'Chirchiq shahri', 'owner_name' => 'XODJAYEVA XANAY MAVLYANKULOVNA',
                'share' => '100%', 'rights_type' => 'Mulk huquqi', 'registration_date' => '2025-11-03',
                'rights_basis' => 'notarial idoraning 27.10.2025 yildagi R-202501815006054 son oldi-sotdi shartnomasi', 'encumbrances' => null,
                'total_area' => 55.21, 'living_area' => 30.96, 'useful_area' => null, 'land_area' => null,
                'floors_total' => null, 'floor' => null, 'rooms' => null, 'year_built' => 3000,
            ],
            'notes' => null,
        ];
    }

    public function test_documents_are_read_reviewed_and_applied(): void
    {
        $this->fakeAi($this->answer());
        $id = $this->upload('pasport.jpg');
        $extract = $this->upload('kadastr.jpg');

        $this->post('/admin/real-estates/'.$this->flat->id.'/documents/read', ['files' => [$id, $extract]])
            ->assertRedirect('/admin/real-estates/'.$this->flat->id.'/documents/review');

        $this->assertCount(2, $this->sent);
        $image = imagecreatefromstring(base64_decode($this->sent[0]['data']));
        $this->assertLessThanOrEqual(2000, max(imagesx($image), imagesy($image)), 'Large photos are scaled down.');
        $this->assertSame(1, Activity::where('event', 'documents_read')->count());

        $this->get('/admin/real-estates/'.$this->flat->id.'/documents/review')->assertOk()
            ->assertSee('Xodjayeva')
            ->assertSee('40808530470016')
            ->assertSee('11:20:01:08:01:0079:0001:051')
            ->assertSee('Chirchiq shahri');

        // The appraiser takes everything except the living area.
        $this->post('/admin/real-estates/'.$this->flat->id.'/documents/apply', [
            'person' => array_fill_keys(['last_name', 'first_name', 'middle_name', 'birth_date', 'pinfl', 'passport_series', 'passport_number', 'passport_issued_by', 'passport_issued_at'], '1'),
            'property' => ['cadastral_number' => '1', 'total_area' => '1', 'address' => '1', 'place' => '1', 'year_built' => '1'],
            'report' => ['rights' => '1', 'rights_basis' => '1'],
        ])->assertRedirect('/admin/real-estates/'.$this->flat->id.'/report/edit');

        $flat = $this->flat->fresh(['clients', 'region', 'district']);
        $owner = $flat->clients->sole();
        $this->assertSame('owner', $owner->pivot->role);
        $this->assertSame('Xodjayeva', $owner->last_name);
        $this->assertSame('AE', $owner->passport_series);
        $this->assertSame('1953-08-08', $owner->birth_date->toDateString());
        $this->assertSame('11:20:01:08:01:0079:0001:051', $flat->cadastral_number);
        $this->assertEquals(55.21, $flat->total_area);
        $this->assertNull($flat->living_area, 'Not ticked.');
        $this->assertNull($flat->year_built, 'A year in the future is not accepted.');
        $this->assertSame('Toshkent viloyati', $flat->region->name_uz);
        $this->assertSame('Chirchiq shahri', $flat->district->name_uz);
        $this->assertSame('Mulk huquqi (100%)', $flat->report['rights']);
        $this->assertStringContainsString('R-202501815006054', $flat->report['rights_basis']);
        $this->assertSame('document', $flat->report['attachments'][$id]);
        $this->assertSame('document', $flat->report['attachments'][$extract]);
    }

    public function test_an_existing_client_is_linked_instead_of_a_duplicate(): void
    {
        $existing = Client::factory()->create(['type' => 'individual', 'pinfl' => '40808530470016', 'last_name' => 'Xodjayeva']);
        $this->fakeAi($this->answer());
        $id = $this->upload('id.jpg');

        $this->post('/admin/real-estates/'.$this->flat->id.'/documents/read', ['files' => [$id]]);
        $this->get('/admin/real-estates/'.$this->flat->id.'/documents/review')->assertSee($existing->title);
        $this->post('/admin/real-estates/'.$this->flat->id.'/documents/apply', ['person' => ['passport_number' => '1']]);

        $this->assertSame([$existing->id], $this->flat->fresh()->clients->pluck('id')->all());
        $this->assertSame(1, Client::count());
        $this->assertSame('3843163', $existing->fresh()->passport_number);
    }

    public function test_only_files_of_the_object_are_read_and_operators_cannot_change_completed_objects(): void
    {
        $this->fakeAi($this->answer());
        $other = RealEstate::factory()->create();
        $foreign = $other->attachments()->create(['name' => 'x.jpg', 'path' => 'x.jpg', 'mime' => 'image/jpeg', 'size' => 1]);

        $this->actingAs($this->appraiser)->post('/admin/real-estates/'.$this->flat->id.'/documents/read', ['files' => [$foreign->id]])
            ->assertSessionHas('error');
        $this->assertSame([], $this->sent);

        $operator = User::factory()->operator()->create();
        $this->flat->update(['status' => ValuationStatus::Completed]);
        $this->actingAs($operator)->post('/admin/real-estates/'.$this->flat->id.'/documents/read', ['files' => [1]])->assertForbidden();
    }

    public function test_without_a_key_the_documents_are_not_sent(): void
    {
        config(['services.anthropic.key' => null]);
        $id = $this->upload('id.jpg');

        $this->post('/admin/real-estates/'.$this->flat->id.'/documents/read', ['files' => [$id]])->assertSessionHas('error');
        $this->get('/admin/real-estates/'.$this->flat->id.'/report/edit')->assertOk()->assertSee('ANTHROPIC_API_KEY');
    }
}
