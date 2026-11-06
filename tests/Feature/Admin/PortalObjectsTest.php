<?php

namespace Tests\Feature\Admin;

use App\Enums\PersonRole;
use App\Enums\ValuationStatus;
use App\Models\Asset;
use App\Models\Client;
use App\Models\DamageCase;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Format;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Mixed property, vehicles and damaged cars in the classic look, as on the
 * valuation portal: their lists, and one page that adds an object with its
 * documents and keeps working on it afterwards; no notes or explanations.
 */
class PortalObjectsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RegionSeeder::class);
        $this->user = User::factory()->admin()->create(['name' => 'SAYDALIYEV ASLIDDIN']);
        $this->company = Client::factory()->create(['type' => 'legal', 'company_name' => 'INVEST-TOSH MCHJ', 'director' => null]);
    }

    public function test_the_list_of_mixed_property_is_the_portals(): void
    {
        $asset = Asset::factory()->create([
            'name' => 'Noturar bino', 'service_fee' => 6_000_000, 'paid_amount' => 0, 'market_value' => 149_301_812,
            'status' => ValuationStatus::InProgress, 'created_by' => $this->user->id,
        ]);
        $asset->clients()->attach($this->company, ['role' => PersonRole::Owner->value]);

        $this->actingAs($this->user)->withSession(['locale' => 'uz'])->get('/admin/assets')->assertOk()
            ->assertSee('<title>Aralash mulklarni baholash ro‘yxati', false)
            ->assertSeeInOrder(['Izlash', 'Statusni tanlang', 'Viloyati', 'Tumani', 'Sana (dan)', 'Sana (gacha)', 'Jami: 1', 'Qo‘shish'])
            ->assertSeeInOrder(['#', 'Obyekt', 'Mulk egasi', 'Reg joyi', 'Kontrakt summasi', 'Baholangan qiymati', 'Status', 'Kiritilgan sana', 'Tomonidan yaratilgan', 'Jarayon'])
            ->assertSeeInOrder(['Noturar bino', 'Kontrakt summasi: '.Format::money(6_000_000), 'To‘langan summa: '.Format::money(0), 'To‘lanmagan',
                '. . .', 'INVEST-TOSH MCHJ', Format::money(149_301_812), 'Baholash jarayonida', $asset->created_at->format('d/m/Y'), 'SAYDALIYEV ASLIDDIN'])
            // The category filter of the modern look is not on the portal.
            ->assertDontSee('name="category"', false);
    }

    public function test_mixed_property_is_added_with_its_documents_on_one_page(): void
    {
        $this->actingAs($this->user)->withSession(['locale' => 'uz'])->get('/admin/assets/create')->assertOk()
            ->assertSee('<title>Aralash mol-mulk qo‘shish', false)
            ->assertSeeInOrder([
                'Tegishli mulkni turi', 'Boshqa', 'Mulk egasi', 'Ishonchnoma bilan / Boshqa tashkilot buyurtmasi qatnashyaptimi',
                'Baholash maqsadi', 'Obyekt', 'Mulkchilik shakli', 'Baholovchi', 'Kontrakt summasi', 'QQS bilan', 'To‘langan summa',
                'Aralash mol-mulkni baholash uchun taqdim qilinadigan hujjatlar',
                'Pasport nusxasi', 'Faylni yuklash uchun bu yerga bosing!', 'Akt', 'Qaror', 'Ijro varaqasi',
                'Holat', 'Hali ko‘rilmadi', 'Baholovchi tomonidan hisobot', 'Direktor tomonidan hisob-varaq faktura',
                'Direktor tomonidan hisobot', 'Buyurtmachi tomonidan hisob-varaq faktura', 'Bekor qilish', 'Saqlash',
            ])
            ->assertSee('enctype="multipart/form-data"', false)
            // None of the extras of the modern form.
            ->assertDontSee('name="notes"', false)->assertDontSee('name="contract_number"', false)
            ->assertDontSee('name="approaches[]"', false)
            ->assertDontSee('Bo‘sh qoldirilsa')->assertDontSee('Yakunlanganda avtomatik');

        // What the portal asks for is required.
        $this->post('/admin/assets', ['owner_id' => '', 'name' => ''])
            ->assertSessionHasErrors(['owner_id', 'name', 'property_type', 'ownership_form', 'purpose', 'appraiser_id']);

        Storage::fake('local');
        $this->post('/admin/assets', [
            'owner_id' => $this->company->id, 'property_type' => 'Boshqa', 'ownership_form' => 'Xususiy mulk', 'purpose' => 'collateral',
            'name' => 'Konteyner yarim tirkamasi 16 dona', 'appraiser_id' => $this->user->id, 'service_fee' => '4 800 000', 'paid_amount' => '',
            'documents' => ['act' => [UploadedFile::fake()->create('olish.pdf', 20, 'application/pdf')], 'writ' => [UploadedFile::fake()->create('varaqa.pdf', 20, 'application/pdf')]],
        ])->assertRedirect();

        $asset = Asset::firstOrFail();
        $this->assertSame('Konteyner yarim tirkamasi 16 dona', $asset->name);
        $this->assertSame(['Boshqa', 'Xususiy mulk', 'collateral'], [$asset->property_type, $asset->ownership_form, $asset->purpose]);
        $this->assertSame(ValuationStatus::New, $asset->status);
        $this->assertEquals(1, $asset->quantity);
        $this->assertEquals(4_800_000, $asset->service_fee);
        $this->assertSame($this->company->id, $asset->owner()->id);
        $this->assertSame(['act' => 'olish.pdf', 'writ' => 'varaqa.pdf'], $asset->attachments()->pluck('name', 'kind')->sortKeys()->all());
        Storage::disk('local')->assertExists($asset->attachments()->first()->path);

        // Afterwards the same page holds its files, its data and its value; editing opens it too.
        $this->get('/admin/assets/'.$asset->id.'/edit')->assertOk()
            ->assertSeeInOrder(['Akt', 'olish.pdf', 'Ijro varaqasi', 'varaqa.pdf', 'Mol-mulk ma’lumotlari', 'Baholash', 'Holat', 'Saqlash'])
            ->assertSee('action="'.route('admin.assets.update', $asset->id).'"', false)
            ->assertSee('form="delete-file-', false);

        $this->put('/admin/assets/'.$asset->id, [
            'owner_id' => $this->company->id, 'property_type' => 'Boshqa', 'ownership_form' => 'Xususiy mulk', 'purpose' => 'collateral',
            'name' => 'Konteyner', 'appraiser_id' => $this->user->id, 'quantity' => '16', 'unit' => 'pcs',
            'approaches' => ['', 'cost'], 'value_cost' => '90 000 000', 'market_value' => '90 000 000',
        ])->assertRedirect();
        $asset->refresh();
        $this->assertEquals(16, $asset->quantity);
        $this->assertSame(['cost'], $asset->approaches);
        $this->assertEquals(90_000_000, $asset->market_value);
        $this->assertSame(ValuationStatus::New, $asset->status);
        $this->assertSame(2, $asset->attachments()->count());
    }

    public function test_a_vehicle_is_added_before_its_make_and_model_are_known(): void
    {
        $this->actingAs($this->user)->withSession(['locale' => 'uz'])->get('/admin/vehicles/create')->assertOk()
            ->assertSee('<title>Avtotransport qo‘shish', false)
            ->assertSeeInOrder([
                'Tegishli mulkni turi', 'Mulk egasi', 'Ishonchnoma bilan', 'Baholash maqsadi', 'Mulkchilik shakli', 'Baholovchi',
                'Kontrakt summasi', 'Identifikatsiya bo‘yicha baholovchi fikri', 'Cheklovchi shartlar bo‘yicha baholovchi fikri',
                'Avtotransportni baholash uchun taqdim qilinadigan hujjatlar', 'Pasport nusxasi', 'Akt', 'Qaror', 'Ijro varaqasi',
                'Bekor qilish', 'Saqlash',
            ])
            ->assertDontSee('name="brand"', false)->assertDontSee('name="notes"', false)->assertDontSee('Holat');

        $this->post('/admin/vehicles', [
            'owner_id' => $this->company->id, 'property_type' => 'Boshqa', 'ownership_form' => 'Xususiy mulk', 'purpose' => 'sale',
            'appraiser_id' => $this->user->id, 'identification' => 'Raqamlar mos', 'restrictions' => 'Yo‘q',
        ])->assertRedirect();
        $car = Vehicle::firstOrFail();
        $this->assertNull($car->brand);
        $this->assertSame(['Raqamlar mos', 'Yo‘q'], [$car->identification, $car->restrictions]);
        $this->assertSame(__('Vehicle').' № '.$car->id, $car->title);

        // Its data, the end of the valuation and the refusal are on its page afterwards.
        $this->get('/admin/vehicles/'.$car->id)->assertOk()
            ->assertSeeInOrder(['Avtotransportni baholash uchun taqdim qilinadigan hujjatlar', 'Avtotransport ma’lumotlari', 'Markasi', 'Baholash',
                'Bekor qilish', 'Saqlash', 'Baholashni tugatish', 'Rad qilish'])
            ->assertDontSee('name="brand" type="text" value="" required', false);

        $this->put('/admin/vehicles/'.$car->id, [
            'owner_id' => $this->company->id, 'property_type' => 'Boshqa', 'ownership_form' => 'Xususiy mulk', 'purpose' => 'sale',
            'appraiser_id' => $this->user->id, 'brand' => 'BYD', 'model' => 'Song PLUS', 'year_made' => 2024, 'condition' => 'Juda yaxshi',
        ])->assertRedirect();
        $this->assertSame('BYD Song PLUS 2024 - yil Juda yaxshi', $car->refresh()->portalName());
    }

    public function test_the_list_of_vehicles_is_the_portals(): void
    {
        $done = Vehicle::factory()->create([
            'brand' => 'BYD', 'model' => 'Song PLUS', 'year_made' => 2024, 'condition' => 'Juda yaxshi', 'purpose' => 'collateral',
            'market_value' => 374_550_000, 'status' => ValuationStatus::Completed, 'completed_at' => now(), 'appraiser_id' => $this->user->id,
        ]);
        $done->steps()->create(['action' => 'submitted', 'user_id' => $this->user->id]);
        $done->steps()->create(['action' => 'approved', 'user_id' => $this->user->id]);
        $new = Vehicle::factory()->create(['status' => ValuationStatus::New, 'appraiser_id' => $this->user->id, 'purpose' => 'sale']);
        $new->clients()->attach($this->company, ['role' => PersonRole::Owner->value]);

        $this->actingAs($this->user)->withSession(['locale' => 'uz'])->get('/admin/vehicles')->assertOk()
            ->assertSee('<title>Avtotransport vositalari ro‘yxati', false)
            ->assertSeeInOrder(['Izlash', 'Statusni tanlang', 'Sana (dan)', 'Sana (gacha)', 'Qo‘shish'])
            ->assertSeeInOrder(['ID raqami', 'Mulk egasi', 'Baholash obyekti nomi', 'Ilova hujjatini ko‘rish', 'Baholangan qiymati', 'Status',
                'Kiritilgan sana', 'Tomonidan yaratilgan baholovchi', 'Baholovchi', 'Direktor', 'Jarayon'])
            ->assertSeeInOrder([$new->id, 'Sotish', 'INVEST-TOSH MCHJ', 'Akt', 'Baholovchiga biriktirildi', 'Tasdiqlash'])
            ->assertSeeInOrder([$done->id, 'Garov', 'BYD Song PLUS 2024 - yil Juda yaxshi', Format::money(374_550_000), 'Tasdiqlangan',
                'Baholovchi tasdiqlagan', 'Direktor tasdiqlagan'])
            ->assertDontSee('Jami:')->assertDontSee('name="region_id"', false);
    }

    public function test_damaged_cars_are_the_list_of_cars_of_the_portal(): void
    {
        $case = DamageCase::factory()->create([
            'vehicle_name' => 'Lacetti (Gentra) AT', 'year_made' => 2023, 'service_fee' => 500_000, 'paid_amount' => 0,
            'status' => ValuationStatus::Completed, 'completed_at' => now(), 'appraiser_id' => $this->user->id,
        ]);

        $this->actingAs($this->user)->withSession(['locale' => 'uz'])->get('/admin/damage-cases')->assertOk()
            ->assertSee('<title>Avtolar ro‘yxati', false)
            ->assertSeeInOrder(['Avtolar ro‘yxati', 'Avto ehtiyot qismlar', 'Kichik ehtiyot qismlar', 'Avtotransport qismlarini ta’mir muddati normalari', 'Avtomobil modellari'])
            ->assertSeeInOrder(['ID raqami', 'Mulk egasi', 'Baholash obyekti nomi', 'Baholangan qiymati', 'Status', 'Kiritilgan sana', 'Tomonidan yaratilgan baholovchi', 'Jarayon'])
            ->assertSeeInOrder([$case->id, 'Yetkazilgan moddiy zarar miqdorini aniqlash', 'Lacetti (Gentra) AT 2023 - yil', 'Kontrakt summasi + QQS', 'To‘lanmagan', 'Tasdiqlangan'])
            ->assertSee('href="'.$case->verifyUrl().'"', false);

        // The car is added with the damage worked out on the same page, without the explanations.
        $this->get('/admin/damage-cases/create')->assertOk()
            ->assertSeeInOrder(['Mulk egasi', 'Baholash maqsadi', 'Baholovchi', 'Kontrakt summasi', 'Avtomobil va hodisa', 'Transport vositasi',
                'Zarar hisob-kitobi', 'Zararni baholash uchun taqdim qilinadigan hujjatlar', 'Saqlash'])
            ->assertDontSee('name="notes"', false)->assertDontSee('Faqat ehtiyot qismlarga');
    }

    public function test_the_modern_look_keeps_its_form(): void
    {
        Setting::put(['admin_theme' => 'modern']);

        $this->actingAs($this->user)->withSession(['locale' => 'uz'])->get('/admin/assets/create')->assertOk()
            ->assertSee('name="notes"', false)->assertSee('name="quantity"', false)->assertDontSee('name="owner_id"', false);
        $this->post('/admin/vehicles', ['status' => 'new'])->assertSessionHasErrors(['brand', 'model']);
    }
}
