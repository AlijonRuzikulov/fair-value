<?php

namespace Tests\Feature\Report;

use App\Enums\ValuationStatus;
use App\Models\Activity;
use App\Models\Client;
use App\Models\RealEstate;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vehicle;
use App\Reports\ContractPack;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The service contract, the act of acceptance, the invoice and the
 * valuation assignment printed for the customer.
 */
class ContractPackTest extends TestCase
{
    use RefreshDatabase;

    private User $appraiser;

    private RealEstate $flat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RegionSeeder::class);
        $this->appraiser = User::factory()->create();
        Setting::put([
            'legal_name' => '"FAIR VALUE" MChJ', 'director' => "SAYDALIYEV ASLIDDIN ILXOM O'G'LI",
            'insurance_policy' => '№ 120/3010/2600637, Infinity Insurance AJ ST, 22.06.2026.', 'bank_account' => '2020 8000 8054 6125 8001',
        ]);

        $this->flat = RealEstate::factory()->residential()->create([
            'name' => 'Turar joy (2 xonali kvartira)', 'address' => 'Feruza ko‘chasi, 26-uy, 39-xonadon', 'purpose' => 'collateral',
            'contract_number' => '668-26', 'contract_date' => '2026-10-20', 'report_number' => '20/551-26', 'service_fee' => 1_500_000,
            'market_value' => 830_177_000, 'status' => ValuationStatus::InProgress, 'appraiser_id' => $this->appraiser->id, 'report' => null,
        ]);
        $customer = Client::factory()->create([
            'type' => 'individual', 'last_name' => 'Jurayev', 'first_name' => 'Shokirjon', 'middle_name' => 'Ganijonovich',
            'passport_series' => 'AD', 'passport_number' => '7652915', 'phone' => '+998941221274',
        ]);
        $this->flat->clients()->attach($customer->id, ['role' => 'owner']);
    }

    public function test_the_four_documents_are_printed_with_the_data_of_the_valuation(): void
    {
        $page = $this->actingAs($this->appraiser)->get('/admin/real-estates/'.$this->flat->id.'/contract')->assertOk();

        $page->assertSee('SHARTNOMA')->assertSee('DALOLATNOMASI')->assertSee('HISOB VARAQ-FAKTURA')->assertSee('VAZIFA')
            ->assertSee('№ 668-26')->assertSee('20.10.2026')
            ->assertSee('Jurayev Shokirjon Ganijonovich')->assertSee('AD 7652915')
            ->assertSee('Sh.G.Jurayev')->assertSee('A.I.Saydaliyev')
            ->assertSee('1 500 000,00')->assertSee('Bir million besh yuz ming')->assertSee('QQS siz')
            ->assertSee('830 177 000,00')->assertSee('Sakkiz yuz o‘ttiz million bir yuz yetmish yetti ming', false)
            // The contract terms with the placeholders filled in.
            ->assertSee('Baholash obyekti: Turar joy (2 xonali kvartira).', false)
            ->assertSee('Infinity Insurance AJ ST, 22.06.2026.')
            ->assertDontSee('{object}')->assertDontSee('data-qr', false);

        $this->assertSame(1, Activity::where('event', 'printed')->count());
    }

    public function test_cyrillic_documents_keep_names_as_typed_and_can_carry_qr_codes(): void
    {
        $page = $this->actingAs($this->appraiser)->get('/admin/real-estates/'.$this->flat->id.'/contract?script=cyrl&qr=1')->assertOk();

        $page->assertSee('ШАРТНОМА')->assertSee('Бир миллион беш юз минг')
            ->assertSee('Jurayev Shokirjon Ganijonovich')->assertDontSee('Жураев')
            ->assertSee('data-qr="'.$this->flat->verifyUrl().'"', false);
    }

    public function test_vat_is_shown_when_the_company_pays_it(): void
    {
        Setting::put(['vat_rate' => '12']);
        $this->flat->update(['service_fee' => 1_120_000]);

        $this->assertSame(['total' => 1_120_000.0, 'net' => 1_000_000.0, 'vat' => 120_000.0, 'rate' => 12.0], ContractPack::fee(1_120_000));
        $this->actingAs($this->appraiser)->get('/admin/real-estates/'.$this->flat->id.'/contract?doc=invoice')->assertOk()
            ->assertSee('HISOB VARAQ-FAKTURA')->assertDontSee('DALOLATNOMASI')
            ->assertSee('1 000 000,00')->assertSee('120 000,00')->assertSee('QQS bilan');
    }

    public function test_the_company_edits_the_terms_and_typed_values_are_escaped(): void
    {
        Setting::put(['report_text_contract' => "## 1. Predmet\n\n1.1. Buyurtmachi {customer} xizmat haqini {fee} so'm to'laydi."]);
        Client::query()->update(['last_name' => '<b>Jurayev</b>']);

        $this->actingAs($this->appraiser)->get('/admin/real-estates/'.$this->flat->id.'/contract?script=cyrl&doc=contract')->assertOk()
            ->assertSee('1.1. Буюртмачи &lt;b&gt;Jurayev&lt;/b&gt; Shokirjon Ganijonovich хизмат ҳақини 1 500 000,00 сўм тўлайди.', false)
            ->assertDontSee('<b>Jurayev</b>', false);
    }

    public function test_other_objects_have_the_documents_too(): void
    {
        $car = Vehicle::factory()->create(['contract_number' => '700-26', 'service_fee' => 300_000]);

        $this->actingAs($this->appraiser)->get('/admin/vehicles/'.$car->id.'/contract')->assertOk()
            ->assertSee('№ 700-26')->assertSee('300 000,00')->assertSee('VAZIFA');
        $this->get('/admin/vehicles/'.$car->id)->assertOk()->assertSee('/admin/vehicles/'.$car->id.'/contract', false);
    }

    public function test_short_names(): void
    {
        $this->assertSame('A.I.Saydaliyev', ContractPack::shortPersonName("SAYDALIYEV ASLIDDIN ILXOM O'G'LI"));
        $this->assertSame('A. Saydaliyev', ContractPack::shortPersonName('A. Saydaliyev'));
        $this->assertSame('Sh.G.Jurayev', ContractPack::shortName(Client::first()));
        $this->assertSame('O‘.Ch.Karimov', ContractPack::shortName(new Client(['type' => 'individual', 'last_name' => 'Karimov', 'first_name' => "O'tkir", 'middle_name' => 'Chori o‘g‘li'])));
    }
}
