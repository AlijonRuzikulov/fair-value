<?php

namespace Tests\Feature\Admin;

use App\Enums\DocumentKind;
use App\Enums\ValuationStatus;
use App\Models\Activity;
use App\Models\Attachment;
use App\Models\RealEstate;
use App\Models\User;
use App\Reports\RealEstateReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Documents of a valuation object in their places (passport, application,
 * cadastral document, photos…), turning pictures and small copies.
 */
class DocumentSlotsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private RealEstate $flat;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->create();
        $this->flat = RealEstate::factory()->residential()->create(['status' => ValuationStatus::InProgress]);
    }

    private function upload(array $files, ?string $kind = null): void
    {
        $this->actingAs($this->user)->post('/admin/attachments', array_filter([
            'type' => 'real_estate', 'id' => $this->flat->id, 'files' => $files, 'kind' => $kind,
        ]))->assertRedirect();
    }

    public function test_the_kind_is_chosen_or_told_by_the_name_and_missing_documents_are_shown(): void
    {
        $this->actingAs($this->user)->withSession(['locale' => 'uz'])->get('/admin/real-estates/'.$this->flat->id)->assertOk()
            ->assertSee('Yetishmayapti: 3')->assertSee('Pasport (ID-karta) nusxasi')->assertSee('Kadastr hujjati');

        $this->upload([UploadedFile::fake()->image('pasport-oldi.jpg'), UploadedFile::fake()->create('ariza.pdf', 20, 'application/pdf'), UploadedFile::fake()->image('IMG_2041.jpg')]);
        $this->upload([UploadedFile::fake()->image('scan.jpg')], DocumentKind::Cadastral->value);

        $kinds = Attachment::orderBy('id')->pluck('kind', 'name')->all();
        $this->assertSame(['pasport-oldi.jpg' => 'passport', 'ariza.pdf' => 'application', 'IMG_2041.jpg' => 'photo', 'scan.jpg' => 'cadastral'], $kinds);

        $this->get('/admin/real-estates/'.$this->flat->id)->assertOk()
            ->assertSee('Barcha hujjatlar bor')
            ->assertSee('thumb=1', false);

        // Report: photos to the photo pages, scans to the appendix, the application is left out.
        $roles = RealEstateReport::attachmentRoles($this->flat->fresh('attachments'), [])->pluck('report_role', 'name')->all();
        $this->assertSame(['pasport-oldi.jpg' => 'document', 'IMG_2041.jpg' => 'photo', 'scan.jpg' => 'document'], $roles);
    }

    public function test_a_picture_is_turned_and_its_small_copy_is_made_again(): void
    {
        $this->upload([UploadedFile::fake()->image('fasad.jpg', 300, 200)], DocumentKind::Photo->value);
        $file = Attachment::sole();

        $thumb = $this->get('/admin/attachments/'.$file->id.'?thumb=1')->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertSame([300, 200], array_slice(getimagesizefromstring($thumb->streamedContent()), 0, 2));
        $this->assertSame(0, Activity::where('event', 'downloaded')->count(), 'Small copies are not logged as opened files.');

        $this->post('/admin/attachments/'.$file->id.'/rotate', ['direction' => 'right'])->assertRedirect()->assertSessionHas('status');
        $this->assertSame([200, 300], array_slice(getimagesize(Storage::disk('local')->path($file->path)), 0, 2));
        $this->assertSame(1, Activity::where('event', 'file_changed')->count());

        $thumb = $this->get('/admin/attachments/'.$file->id.'?thumb=1')->assertOk();
        $this->assertSame([200, 300], array_slice(getimagesizefromstring($thumb->streamedContent()), 0, 2));
        $this->assertCount(1, Storage::disk('local')->files('thumbs/'.$file->id), 'The old small copy is removed.');

        $big = UploadedFile::fake()->image('big.jpg', 2400, 1200);
        $this->upload([$big], DocumentKind::Photo->value);
        $thumb = $this->get('/admin/attachments/'.Attachment::latest('id')->value('id').'?thumb=1')->assertOk();
        $this->assertSame([360, 180], array_slice(getimagesizefromstring($thumb->streamedContent()), 0, 2));
    }

    public function test_the_kind_is_changed_and_completed_valuations_are_protected(): void
    {
        $this->upload([UploadedFile::fake()->image('IMG_1.jpg')]);
        $file = Attachment::sole();

        $this->patch('/admin/attachments/'.$file->id, ['kind' => 'technical_passport'])->assertRedirect();
        $this->assertSame('technical_passport', $file->fresh()->kind);
        $this->patch('/admin/attachments/'.$file->id, ['kind' => 'nonsense'])->assertSessionHasErrors('kind');

        $this->flat->update(['status' => ValuationStatus::Completed]);
        $operator = User::factory()->operator()->create();
        $this->actingAs($operator)->patch('/admin/attachments/'.$file->id, ['kind' => 'photo'])->assertForbidden();
        $this->post('/admin/attachments/'.$file->id.'/rotate', ['direction' => 'left'])->assertForbidden();
        $this->post('/admin/attachments', ['type' => 'real_estate', 'id' => $this->flat->id, 'files' => [UploadedFile::fake()->image('x.jpg')]])->assertForbidden();
        $this->get('/admin/real-estates/'.$this->flat->id)->assertOk()->assertDontSee('/rotate', false);
    }
}
