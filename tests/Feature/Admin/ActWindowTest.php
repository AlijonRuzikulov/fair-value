<?php

namespace Tests\Feature\Admin;

use App\Enums\PersonRole;
use App\Models\Attachment;
use App\Models\Client;
use App\Models\RealEstate;
use App\Models\User;
use App\Support\Format;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * "Akt" of the valuation portal: a window over the list and the object page
 * with the valued amount, the approaches, the documents to look at and the
 * data of the object taken at the inspection.
 */
class ActWindowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private RealEstate $house;

    private Client $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RegionSeeder::class);
        Storage::fake('local');
        $this->user = User::factory()->create();
        $this->owner = Client::factory()->create(['last_name' => 'Musinova', 'first_name' => 'Dilorom', 'middle_name' => 'Abdullayevna']);
        $this->house = RealEstate::factory()->residential()->create(['market_value' => 1600263169.12, 'approaches' => ['comparative'], 'value_comparative' => 1600263169.12]);
        $this->house->clients()->attach($this->owner->id, ['role' => PersonRole::Owner->value]);
        Attachment::keep($this->owner, UploadedFile::fake()->image('pasport.jpg', 400, 300), 'passport', $this->user->id);
        Attachment::keep($this->house, UploadedFile::fake()->image('kadastr.jpg', 300, 400), 'cadastral', $this->user->id);
    }

    private function form(array $changes = []): array
    {
        return [
            'approaches' => ['', 'cost', 'comparative'], 'owner_id' => (string) $this->owner->id, 'function' => 'Turar joy',
            'architecture' => 'Evro', 'amenities' => ['', "Asosiy yo'l yoqasida joylashgan", "Metro stansiyasidan 200 metr radius oralig'ida joylashgan"],
            'repair' => 'Qoniqarli', 'completion' => '100', 'ownership_form' => 'Jamiyat mulki', 'land_rights' => 'Ijara huquqi',
            ...$changes,
        ];
    }

    public function test_the_act_opens_in_a_window_from_the_list(): void
    {
        $this->actingAs($this->user)->withSession(['locale' => 'uz']);
        $list = $this->get('/admin/real-estates?kind=residential')->assertOk();
        $list->assertSee(route('admin.real-estates.act', $this->house->id).'" data-act-open', false);

        // In the window: the valued amount, the approaches, the pictures of the documents and the fields of the portal.
        $window = $this->get('/admin/real-estates/'.$this->house->id.'/act', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertDontSee('<html', false)
            ->assertSee('Baholangan qiymat: '.Format::number(1600263169.12, 2).' so‘m')
            ->assertSee('data-act-close', false)
            ->assertSeeInOrder(['Xarajat yondashuvi', 'Daromad yondashuvi', 'Qiyosiy yondashuv'])
            ->assertSeeInOrder(['Musinova Dilorom Abdullayevna', 'Foydalanish maqsadi', 'Arxitekturaviy uslub (fasad)', 'Alohida qulayliklar',
                'Ta’mir holati', 'Qurilish tugallanganligi (%)', 'Mulkchilik shakli', 'Yer uchastkasiga bo‘lgan mulk huquqi']);
        $this->assertSame(2, substr_count($window->getContent(), 'data-act-slide'));
        $window->assertSee('Asosiy yo‘l yoqasida joylashgan')->assertSee('Jamiyat mulki')->assertSee('Ijara huquqi')->assertSee('data-act-rotate', false);

        // Without the window (a new tab) it is a page.
        $this->get('/admin/real-estates/'.$this->house->id.'/act')->assertOk()->assertSee('<html', false)->assertSee('act-page', false);
    }

    public function test_the_act_is_saved_and_the_list_shows_it(): void
    {
        $other = Client::factory()->create(['last_name' => 'Bankov']);
        $this->house->clients()->attach($other->id, ['role' => PersonRole::Pledgee->value]);
        $list = '/admin/real-estates?kind=residential';

        $this->actingAs($this->user)->withSession(['locale' => 'uz'])->from($list)
            ->put('/admin/real-estates/'.$this->house->id.'/act', $this->form(['_back' => url($list)]))
            ->assertRedirect(url($list))->assertSessionHas('status');

        $house = $this->house->fresh();
        $this->assertSame(['cost', 'comparative'], $house->approaches);
        $this->assertSame(['Evro', 'Qoniqarli', 'Jamiyat mulki', 'Ijara huquqi'], [$house->report['architecture'], $house->report['repair'], $house->report['ownership_form'], $house->report['land_rights']]);
        $this->assertSame(["Asosiy yo'l yoqasida joylashgan", "Metro stansiyasidan 200 metr radius oralig'ida joylashgan"], $house->report['amenities']);
        $this->get($list)->assertSee('Asosiy yo‘l yoqasida joylashgan');

        // Another owner chosen: the pledgee stays.
        $this->put('/admin/real-estates/'.$this->house->id.'/act', $this->form(['owner_id' => (string) $other->id]))->assertRedirect();
        $this->assertSame([PersonRole::Owner->value => $other->id], $this->house->fresh()->clients->mapWithKeys(fn ($c) => [$c->pivot->role => $c->id])->all());
        $this->house->clients()->attach($this->owner->id, ['role' => PersonRole::Pledgee->value]);
        $this->put('/admin/real-estates/'.$this->house->id.'/act', $this->form(['owner_id' => (string) $this->owner->id]))->assertRedirect();
        $this->assertSame([$this->owner->id], $this->house->fresh()->clients->where('pivot.role', PersonRole::Owner->value)->pluck('id')->values()->all());

        // A mistake is shown on the act as a page, with what was typed.
        $this->put('/admin/real-estates/'.$this->house->id.'/act', $this->form(['amenities' => [''], 'approaches' => [''], 'architecture' => 'Barokko']))
            ->assertRedirect(route('admin.real-estates.act', $this->house->id))->assertSessionHasErrors(['amenities', 'approaches']);
        $this->assertSame('Evro', $this->house->fresh()->report['architecture']);
    }

    public function test_the_add_page_writes_the_colons_and_looks_for_the_landmark(): void
    {
        $this->actingAs($this->user)->withSession(['locale' => 'uz'])->get('/admin/real-estates/create?kind=residential')->assertOk()
            ->assertSee('data-cadastral', false)->assertSee('placeholder="10:01:05:01:02:0123"', false)
            ->assertSee('data-landmark-find', false)->assertSee('data-found="Xaritada belgilandi: :place"', false)
            ->assertSee('Alohida qulayliklar')->assertSee('Metro stansiyasidan 200 metr radius oralig‘ida joylashgan');

        // A colon left at the end (the box writes it after each group) is not kept.
        $this->house->update(['cadastral_number' => ' 10:10:03:01:01:5037: ']);
        $this->assertSame('10:10:03:01:01:5037', $this->house->fresh()->cadastral_number);
        // A land plot by the newer rule keeps its slash, but not one left at the end.
        $this->house->update(['cadastral_number' => '10:04:02:01:03:00451/0012']);
        $this->assertSame('10:04:02:01:03:00451/0012', $this->house->fresh()->cadastral_number);
        $this->house->update(['cadastral_number' => '10:04:02:01:03:00451/']);
        $this->assertSame('10:04:02:01:03:00451', $this->house->fresh()->cadastral_number);
    }

    public function test_someone_who_may_not_change_the_valuation_only_looks(): void
    {
        $this->house->update(['status' => 'completed']);
        $operator = User::factory()->operator()->create();

        $this->actingAs($operator)->get('/admin/real-estates/'.$this->house->id.'/act', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertSee('<fieldset class="act-body" disabled', false)->assertDontSee('type="submit"', false)->assertDontSee('data-act-rotate', false);
        $this->put('/admin/real-estates/'.$this->house->id.'/act', $this->form())->assertForbidden();
    }
}
