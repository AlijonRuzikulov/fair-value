<?php

namespace Tests\Feature\Admin;

use App\Enums\PersonRole;
use App\Enums\ValuationStatus;
use App\Models\Client;
use App\Models\RealEstate;
use App\Models\Region;
use App\Models\Setting;
use App\Models\User;
use App\Reports\RealEstateReport;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The real estate page laid out as on the valuation portal: the four steps,
 * the main data saved right on the page, approaches applied or refused,
 * the reconciliation and the status with the history.
 */
class PortalPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private RealEstate $flat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RegionSeeder::class);
        $this->user = User::factory()->create();
        $this->flat = RealEstate::factory()->residential()->create([
            'name' => 'Kvartira', 'total_area' => 60, 'valuation_date' => '2026-10-01', 'contract_number' => '17-26',
            'approaches' => ['comparative', 'income'], 'status' => ValuationStatus::InProgress,
            'value_comparative' => 600_000_000, 'value_income' => 500_000_000, 'market_value' => null,
            'report' => ['income' => ['area' => 60, 'rent' => 150_000, 'vacancy' => 10, 'opex' => 15, 'cap' => 14]],
        ]);
        $this->flat->clients()->attach(Client::factory()->create(), ['role' => PersonRole::Owner->value]);
    }

    public function test_the_page_has_the_steps_and_the_sections_of_the_portal(): void
    {
        $this->actingAs($this->user)->withSession(['locale' => 'uz'])->get('/admin/real-estates/'.$this->flat->id)->assertOk()
            ->assertSee('<title>Ko‘chmas mulkni baholash (Turar joy)', false)
            ->assertSeeInOrder([
                'Ko‘chmas mulk asosiy ma’lumotlarini saqlash', 'Yondashuvlarni hisoblash', 'Muvofiqlashtirish va tasdiqlash', 'Tamom',
                'Ko‘chmas mulk ma’lumotlarini kiriting (Turar joy)', 'Mobil ilova orqali ma’lumot olish',
                'Tegishli mulkni turi', 'Mulk egasi', 'Ishonchnoma bilan / Boshqa tashkilot buyurtmasi qatnashyaptimi',
                'Yondashuv usullari', 'Baholash maqsadi', 'Obyekt', 'Ko‘chmas mulk kadastr raqami', 'Ma’lumotni olish',
                'Alohida qulayliklar', 'Arxitekturaviy uslub (fasad)', 'Foydalanish maqsadi', 'Ta’mir holati',
                'Qurilish tugallanganligi (%)', 'Baholovchi', 'Yengil konstruktiv element', 'Kontrakt summasi', 'QQS bilan',
                'To‘langan summa', 'Identifikatsiya bo‘yicha baholovchi fikri', 'Joylashgan joyi',
                'Ko‘chmas mulk asosiy ma’lumotlari', 'Ko‘chmas mulkni baholash uchun taqdim qilinadigan hujjatlar',
                'Qiyosiy yondashuv', 'Daromad yondashuvi', 'Muvofiqlashtirish', 'Baholanadigan binolarni kiriting',
                'Holat', 'Hali ko‘rilmadi', 'Baholovchi tomonidan hisobot', 'Direktor tomonidan hisob-varaq faktura',
                'Direktor tomonidan hisobot', 'Buyurtmachi tomonidan hisob-varaq faktura', 'Tarixi', 'Sarflangan vaqt', 'Saqlash',
            ])
            ->assertSee('(voz kechildi)')
            ->assertSee('action="'.route('admin.real-estates.main', $this->flat->id).'"', false)
            ->assertSee('name="owner_id" value="'.$this->flat->clients->first()->id.'"', false)
            ->assertSee('name="value_kind" value="market" checked', false)
            ->assertSee('placeholder="17-26"', false);

        $this->withSession(['locale' => 'uz'])->get('/admin/real-estates/'.$this->flat->id)->assertSeeInOrder([
            'Ko‘chmas mulk asosiy ma’lumotlari', 'Kadastr yoki balans qiymati', 'Viloyatni tanlang', 'Tumanni tanlang', 'Mo‘ljalni tanlang',
            'Ko‘chmas mulk manzili', 'Xat №', 'Shartnoma №', 'Hisobot №', 'Ko‘chmas mulk umumiy yer maydoni (kv. m)',
            'Ko‘chmas mulk umumiy qurilish osti maydoni (kv. m)', 'Ko‘chmas mulk umumiy foydali maydon (kv. m)', 'Ko‘chmas mulk umumiy maydon (kv. m)',
            'Umumiy maydondan hisoblash', 'Qurilish turi', 'Pishiq g‘isht', 'Sendvich panel', 'Alohida qurilgan bino', 'Qavatlari soni', '60 qavat', 'Qavati',
            'Qurilgan yili', 'Ko‘chmas mulkni aniqlanayotgan qiymat turini tanlang', 'Mulkchilik shakli', 'Yer uchastkasiga bo‘lgan mulk huquqi',
            'Qiyosiy yondashuv indeks bo‘yicha tuzatish berish', 'Akt ma’lumotlarini kiritish', 'So‘rovnoma ma’lumotlari',
        ]);

        // A new object is added on the same page.
        $this->get('/admin/real-estates/create?kind=nonresidential')->assertOk()
            ->assertSee('action="'.route('admin.real-estates.store').'"', false)
            ->assertSee('<option value="Boshqa" selected>', false)
            ->assertSee('Hujjatlar obyekt saqlangandan keyin yuklanadi.');

        // The modern look keeps the page it had.
        Setting::put(['admin_theme' => 'modern']);
        $this->get('/admin/real-estates/'.$this->flat->id)->assertOk()
            ->assertDontSee('portal-steps', false)->assertSee('Holat va tasdiqlash');
    }

    public function test_the_main_data_is_saved_on_the_page_without_touching_the_rest(): void
    {
        $this->actingAs($this->user)->put('/admin/real-estates/'.$this->flat->id.'/main', [
            'name' => 'Kvartira (3 xonali)', 'cadastral_number' => '10:01:02:03', 'cadastral_value' => '250 000 000,5',
            'total_area' => '72,4', 'floor' => '3', 'floors_total' => '9', 'letter_number' => 'X-5',
            'value_kind' => 'other', 'value_type' => 'Investitsion qiymat', 'ownership_form' => 'Davlat mulki',
            'land_rights' => '', 'function' => "Ko'p qavatli turar joy",
        ])->assertRedirect('/admin/real-estates/'.$this->flat->id);

        $flat = $this->flat->fresh();
        $this->assertSame('Kvartira (3 xonali)', $flat->name);
        $this->assertSame('250000000.50', $flat->cadastral_value);
        $this->assertSame('72.40', $flat->total_area);
        $this->assertSame(9, $flat->floors_total);
        $this->assertSame('Investitsion qiymat', $flat->report['value_type']);
        $this->assertSame('Davlat mulki', $flat->report['ownership_form']);
        $this->assertSame('X-5', $flat->report['letter_number']);
        $this->assertArrayNotHasKey('land_rights', $flat->report);
        // What the page does not have stays: persons, approaches, the income data.
        $this->assertCount(1, $flat->clients);
        $this->assertSame(['comparative', 'income'], $flat->approaches);
        $this->assertSame(150000, $flat->report['income']['rent']);
        $this->assertSame(ValuationStatus::InProgress, $flat->status);

        // Back to the market value; the name is still required.
        $this->put('/admin/real-estates/'.$this->flat->id.'/main', ['name' => 'Kvartira', 'value_kind' => 'market', 'value_type' => 'Tugatish qiymati'])->assertRedirect();
        $this->assertSame(RealEstateReport::MARKET_VALUE, $this->flat->fresh()->report['value_type']);
        $this->put('/admin/real-estates/'.$this->flat->id.'/main', ['name' => '', 'value_kind' => 'other'])->assertSessionHasErrors(['name', 'value_type']);
    }

    public function test_the_full_form_saves_the_report_fields_too(): void
    {
        // In the classic look the edit address shows the object page, which is its form.
        $this->actingAs($this->user)->get('/admin/real-estates/'.$this->flat->id.'/edit')->assertOk()
            ->assertSee('portal-steps', false)->assertSee('name="letter_number"', false);

        $this->put('/admin/real-estates/'.$this->flat->id, [
            'kind' => 'residential', 'name' => 'Kvartira', 'status' => 'in_progress', 'total_area' => '60',
            'approaches' => ['comparative'], 'value_comparative' => '600 000 000',
            'persons' => [['client_id' => $this->flat->clients->first()->id, 'role' => 'owner']],
            'value_kind' => 'market', 'ownership_form' => 'Jamoa mulki', 'letter_number' => '',
        ])->assertRedirect();

        $flat = $this->flat->fresh();
        $this->assertSame('Jamoa mulki', $flat->report['ownership_form']);
        $this->assertSame('Bozor qiymati', $flat->report['value_type']);
        $this->assertArrayNotHasKey('letter_number', $flat->report);
        $this->assertSame('17-26', RealEstateReport::values($flat)['letter_number']);
    }

    public function test_an_approach_is_refused_with_a_reason_and_applied_again(): void
    {
        $this->actingAs($this->user)->put('/admin/real-estates/'.$this->flat->id.'/approaches/income/applied', [
            'applied' => '0', 'reason' => 'Ijara bozori shakllanmagan',
        ])->assertRedirect('/admin/real-estates/'.$this->flat->id.'#approach-income');

        $flat = $this->flat->fresh();
        $this->assertSame(['comparative'], $flat->approaches);
        $this->assertNull($flat->value_income);
        $this->assertSame('Ijara bozori shakllanmagan', RealEstateReport::values($flat)['reason_income']);

        // Applied again: the value is worked out from the data typed before, the standard reason returns.
        $this->put('/admin/real-estates/'.$this->flat->id.'/approaches/income/applied', ['applied' => '1'])->assertRedirect();
        $flat = $this->flat->fresh();
        $this->assertSame(['comparative', 'income'], $flat->approaches);
        $this->assertNotNull($flat->value_income);
        $this->assertArrayNotHasKey('reason_income', $flat->report);

        $this->put('/admin/real-estates/'.$this->flat->id.'/approaches/other/applied', ['applied' => '1'])->assertNotFound();
    }

    public function test_the_final_value_is_typed_or_taken_from_the_reconciliation(): void
    {
        $this->actingAs($this->user)->put('/admin/real-estates/'.$this->flat->id.'/value', ['market_value' => '555 000 000'])
            ->assertRedirect('/admin/real-estates/'.$this->flat->id.'#reconciliation');
        $this->assertSame('555000000.00', $this->flat->fresh()->market_value);

        // Equal scores: half of each value.
        $this->put('/admin/real-estates/'.$this->flat->id.'/value', ['reconciled' => '1'])->assertRedirect();
        $this->assertSame('550000000.00', $this->flat->fresh()->market_value);

        $this->withSession(['locale' => 'uz'])->get('/admin/real-estates/'.$this->flat->id)->assertOk()
            ->assertSee('50,0 %')->assertSee('Muvofiqlashtirilgan qiymatni olish');
    }

    public function test_a_new_object_is_added_with_the_fields_of_the_portal(): void
    {
        $owner = Client::factory()->create();
        $bank = Client::factory()->create();
        $form = [
            'kind' => 'nonresidential', 'property_type' => 'Bino', 'owner_id' => (string) $owner->id,
            'persons' => [['client_id' => (string) $bank->id, 'role' => 'pledgee']],
            'approaches' => ['', 'cost', 'comparative'], 'purpose' => 'collateral', 'name' => 'Savdo binosi',
            'cadastral_number' => '10:09:01:02:03:0101', 'features' => ['', 'electricity', 'gas'],
            'architecture' => 'Zamonaviy', 'function' => 'Noturar joy (savdo)', 'repair' => 'Yaxshi', 'completion' => '85.5',
            'light' => '1', 'service_fee' => '2 000 000', 'paid_amount' => '500 000', 'identification' => 'Obyekt hujjatlarga mos keladi.',
            'total_area' => '120,5', 'value_kind' => 'market', ...$this->mainData(),
        ];

        $this->actingAs($this->user)->post('/admin/real-estates', $form)->assertRedirect();
        $object = RealEstate::where('name', 'Savdo binosi')->sole();

        $this->assertSame(ValuationStatus::New, $object->status);
        $this->assertSame(['cost', 'comparative'], $object->approaches);
        $this->assertSame(['electricity', 'gas'], $object->features);
        $this->assertSame(['owner' => $owner->id, 'pledgee' => $bank->id], $object->clients->mapWithKeys(fn ($c) => [$c->pivot->role => $c->id])->all());
        $this->assertSame('Bino', $object->report['property_type']);
        $this->assertSame('Zamonaviy', $object->report['architecture']);
        $this->assertEquals(85.5, $object->report['completion']);
        $this->assertTrue($object->report['light']);
        $this->assertSame('Obyekt hujjatlarga mos keladi.', $object->report['identification']);
        $this->assertSame('2000000.00', $object->service_fee);

        // What the portal asks for is required on this page.
        $this->post('/admin/real-estates', [...$form, 'owner_id' => '', 'approaches' => [''], 'features' => [''], 'cadastral_number' => '', 'land_area' => '', 'wall_material' => '', 'region_id' => ''])
            ->assertSessionHasErrors(['owner_id', 'approaches', 'features', 'cadastral_number', 'land_area', 'wall_material', 'region_id']);
    }

    public function test_the_owner_and_the_other_persons_are_changed_on_the_page(): void
    {
        $owner = Client::factory()->create();
        $this->actingAs($this->user)->put('/admin/real-estates/'.$this->flat->id.'/main', [
            'owner_id' => (string) $owner->id, 'approaches' => ['', 'comparative'], 'features' => ['', 'water'],
            'purpose' => 'sale', 'name' => 'Kvartira', 'cadastral_number' => '1', 'property_type' => 'Kvartira',
            'architecture' => 'Oddiy', 'function' => 'Turar joy', 'repair' => 'Yaxshi', 'completion' => '100', 'light' => '0',
            ...$this->mainData(), 'detached' => '1', 'floors_total' => '9', 'floor' => '4', 'use_index' => '1', 'market_index' => '0,6',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $flat = $this->flat->fresh();
        $this->assertSame([$owner->id], $flat->clients->pluck('id')->all());
        $this->assertSame(['comparative'], $flat->approaches);
        $this->assertNull($flat->value_income);
        $this->assertArrayNotHasKey('light', $flat->report);
        $this->assertTrue($flat->report['detached']);
        $this->assertSame(0.6, $flat->report['market_index']);
        $this->assertSame([9, 4], [$flat->floors_total, $flat->floor]);
        $this->assertSame("Pishiq g'isht", $flat->wall_material);

        // Unticked, the index is no longer applied.
        $this->put('/admin/real-estates/'.$this->flat->id.'/main', ['name' => 'Kvartira', 'use_index' => '0', 'market_index' => '0,6'])->assertRedirect();
        $this->assertArrayNotHasKey('market_index', $this->flat->fresh()->report);
    }

    /** The required fields of the main data section, as on the portal. */
    private function mainData(): array
    {
        $region = Region::whereHas('districts')->first();

        return [
            'cadastral_value' => '150 000 000', 'region_id' => (string) $region->id, 'district_id' => (string) $region->districts()->first()->id,
            'landmark' => 'Feruza massivi', 'address' => 'Navoiy ko‘chasi, 5', 'land_area' => '120', 'built_up_area' => '100', 'useful_area' => '95',
            'wall_material' => "Pishiq g'isht", 'ownership_form' => 'Xususiy mulk', 'land_rights' => 'Doimiy foydalanish',
        ];
    }

    public function test_the_director_confirms_the_invoice(): void
    {
        $director = User::factory()->admin()->create();
        $this->actingAs($director)->post('/admin/real-estates/'.$this->flat->id.'/workflow', ['action' => 'invoiced'])->assertRedirect();
        $this->post('/admin/real-estates/'.$this->flat->id.'/workflow', ['action' => 'invoiced'])->assertSessionHas('error');

        $this->withSession(['locale' => 'uz'])->get('/admin/real-estates/'.$this->flat->id)->assertOk()
            ->assertSeeInOrder(['Direktor tomonidan hisob-varaq faktura', 'Tasdiqlangan', 'Direktor tomonidan hisobot', 'Tasdiqlanmagan'])
            ->assertSee('Direktor hisob-varaq fakturani tasdiqladi');
        $this->actingAs($this->user)->post('/admin/real-estates/'.$this->flat->id.'/workflow', ['action' => 'invoiced'])->assertForbidden();
    }

    public function test_a_completed_valuation_is_changed_only_by_appraisers(): void
    {
        $this->flat->update(['status' => ValuationStatus::Completed, 'market_value' => 550_000_000]);
        $operator = User::factory()->operator()->create();

        $this->actingAs($operator)->put('/admin/real-estates/'.$this->flat->id.'/main', ['name' => 'X'])->assertForbidden();
        $this->put('/admin/real-estates/'.$this->flat->id.'/value', ['market_value' => '1'])->assertForbidden();
        $this->put('/admin/real-estates/'.$this->flat->id.'/approaches/income/applied', ['applied' => '0'])->assertForbidden();
        $this->get('/admin/real-estates/'.$this->flat->id)->assertOk()
            ->assertSee('<fieldset class="form-grid" disabled>', false)->assertDontSee('Yondashuvdan voz kechish');
        $this->assertSame('Kvartira', $this->flat->fresh()->name);
    }
}
