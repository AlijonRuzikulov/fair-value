<?php

namespace Tests\Feature\Report;

use App\Enums\ValuationStatus;
use App\Http\Controllers\Admin\SettingController;
use App\Models\Activity;
use App\Models\Analog;
use App\Models\Client;
use App\Models\CurrencyRate;
use App\Models\RealEstate;
use App\Models\Setting;
use App\Models\User;
use App\Reports\ReportScript;
use App\Reports\ReportText;
use App\Services\Analogs\AnalogPhoto;
use App\Services\CurrencyService;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The full valuation report of real estate: its form, the printed report,
 * the editable standard texts and the documents appended to it.
 */
class ValuationReportTest extends TestCase
{
    use RefreshDatabase;

    private User $appraiser;

    private RealEstate $flat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RegionSeeder::class);
        $this->appraiser = User::factory()->create(['certificate_number' => '1228', 'certificate_date' => '2026-08-20']);

        foreach ([['USD', '2026-09-01', 11814.05], ['EUR', '2026-09-01', 13482.19], ['RUB', '2026-09-01', 139.93], ['USD', '2026-07-15', 12065.49]] as [$code, $date, $rate]) {
            CurrencyRate::create(['code' => $code, 'nominal' => 1, 'rate' => $rate, 'diff' => 0, 'rate_date' => $date]);
        }

        $this->flat = RealEstate::factory()->residential()->create([
            'name' => 'Turar joy (2 xonali kvartira)', 'cadastral_number' => '11:20:01:08:01:0079:0001:051',
            'total_area' => 55.21, 'floors_total' => 4, 'floor' => 3, 'wall_material' => "Pishgan g'isht", 'condition' => 'Yaxshi',
            'features' => ['electricity', 'gas'], 'purpose' => 'collateral', 'approaches' => ['comparative'],
            'valuation_date' => '2026-09-01', 'inspection_date' => '2026-09-01', 'contract_number' => '643-26',
            'status' => ValuationStatus::InProgress, 'appraiser_id' => $this->appraiser->id,
            'value_comparative' => 514_168_000, 'market_value' => 514_168_000, 'report' => null,
        ]);
        $owner = Client::factory()->create(['type' => 'individual', 'last_name' => 'Xodjayeva', 'first_name' => 'Xanay', 'middle_name' => 'Mavlyankulovna']);
        $this->flat->clients()->attach($owner->id, ['role' => 'owner']);

        foreach ([[25000, 28.9, '2026-07-15'], [24000, 29.49, '2026-07-15'], [20000, 29.46, '2026-07-15']] as $i => [$usd, $area, $date]) {
            $this->flat->analogs()->create([
                'status' => Analog::READY, 'title' => 'Kvartira '.($i + 1), 'url' => 'https://www.olx.uz/d/obyavlenie/'.$i.'.html',
                'price' => $usd, 'currency' => 'USD', 'usd_rate' => 12065.49, 'price_uzs' => round($usd * 12065.49, 2), 'area' => $area,
                'location' => 'Chirchiq shahri, Turon MFY', 'listed_at' => $date, 'adjustments' => ['bargain' => -3], 'included' => true,
            ]);
        }
    }

    public function test_the_report_is_printed_in_cyrillic_and_in_latin(): void
    {
        $this->actingAs($this->appraiser);

        $cyrillic = $this->get('/admin/real-estates/'.$this->flat->id.'/report?script=cyrl')->assertOk();
        $cyrillic->assertSee('БАҲОЛАШ ҲИСОБОТИ')
            ->assertSee('Xodjayeva Xanay Mavlyankulovna', false)      // names stay as typed
            ->assertSee('514 168 000 сўм')
            ->assertSee('Беш юз ўн тўрт миллион бир юз олтмиш саккиз минг сўм', false)
            ->assertSee('Таққослаш элементлари')                      // the adjustment grid
            ->assertSee('11 814,05')                                  // dollar rate on the valuation date
            ->assertSee('-2,08 %', false)->assertDontSee('Bozor qiymati');

        $latin = $this->get('/admin/real-estates/'.$this->flat->id.'/report')->assertOk();
        $latin->assertSee('BAHOLASH HISOBOTI')
            ->assertSee('Besh yuz o‘n to‘rt million bir yuz oltmish sakkiz ming so‘m', false)
            ->assertSee('Taqqoslash elementlari')
            // The QR code for checking the report: on the cover and at the foot of every page.
            ->assertSee('class="qr-run"', false)
            ->assertSee('data-qr="'.$this->flat->verifyUrl().'"', false);

        // Market conditions of the dollar offers: 11 814.05 / 12 065.49 − 1 = −2.08 %.
        $latin->assertSee('-2,08 %', false);

        $this->assertSame(2, Activity::where('event', 'printed')->count());
    }

    public function test_reports_are_in_latin_unless_the_company_chooses_cyrillic(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($this->appraiser)->get('/admin/real-estates/'.$this->flat->id.'/report')->assertSee('BAHOLASH HISOBOTI');

        $settings = collect(SettingController::KEYS)->mapWithKeys(fn ($key) => [$key => Setting::get($key)])->all();
        $this->actingAs($admin)->put('/admin/settings', [...$settings, 'report_script' => ReportScript::CYRILLIC])->assertSessionHasNoErrors();

        $this->actingAs($this->appraiser)->get('/admin/real-estates/'.$this->flat->id.'/report')->assertSee('БАҲОЛАШ ҲИСОБОТИ');
        // A report switched to Latin on its own page stays in Latin.
        $this->put('/admin/real-estates/'.$this->flat->id.'/report', ['script' => ReportScript::LATIN, 'report_date' => '2026-10-12'])->assertSessionHasNoErrors();
        $this->get('/admin/real-estates/'.$this->flat->id.'/report')->assertSee('BAHOLASH HISOBOTI');
    }

    public function test_the_report_form_keeps_only_what_differs_from_the_defaults(): void
    {
        $this->actingAs($this->appraiser);
        $this->get('/admin/real-estates/'.$this->flat->id.'/report/edit')->assertOk()->assertSee('Bozor qiymati');

        $this->put('/admin/real-estates/'.$this->flat->id.'/report', [
            'script' => ReportScript::CYRILLIC, 'report_date' => '2026-10-12', 'value_type' => 'Bozor qiymati',
            'foundation' => 'Lentasimon beton', 'building_volume' => '220,84', 'completion' => '100',
            'scores' => [0 => ['comparative' => 2], 1 => ['comparative' => 1], 2 => ['comparative' => 1], 3 => ['comparative' => 1]],
            'company_documents' => '1', 'include_macro' => '0', 'include_methodology' => '1',
        ])->assertRedirect('/admin/real-estates/'.$this->flat->id.'/report/edit');

        $report = $this->flat->fresh()->report;
        $this->assertSame('cyrl', $report['script']);
        $this->assertSame('Lentasimon beton', $report['foundation']);
        $this->assertEquals(220.84, $report['building_volume']);
        $this->assertArrayNotHasKey('value_type', $report, 'The default value is not stored.');
        $this->assertArrayNotHasKey('completion', $report);
        $this->assertSame(2, $report['scores'][0]['comparative']);
        $this->assertFalse($report['include_macro']);

        $log = Activity::where('event', 'updated')->latest('id')->first();
        $this->assertArrayHasKey('report.foundation', $log->changes);

        $this->get('/admin/real-estates/'.$this->flat->id.'/report')->assertOk()->assertSee('БАҲОЛАШ ҲИСОБОТИ');
        $this->get('/admin/real-estates/'.$this->flat->id.'/report?script=latn')->assertOk()
            ->assertSee('Lentasimon beton')
            ->assertDontSee('Yalpi ichki mahsulot');
    }

    public function test_the_inspection_act_is_saved_weighted_and_printed(): void
    {
        $this->actingAs($this->appraiser);
        // Inputs of the cost approach, saved on the object page, outlive a save of the report form.
        $this->flat->update(['report' => ['cost' => ['buildings' => [['name' => 'Asosiy bino', 'quantity' => 55.21, 'rate' => 5_000_000]], 'profit' => 10, 'land' => 0]]]);
        $this->get('/admin/real-estates/'.$this->flat->id.'/report/edit')->assertOk()->assertSee('name="act[elements][walls][wear]"', false);

        $this->put('/admin/real-estates/'.$this->flat->id.'/report', [
            'script' => ReportScript::LATIN, 'report_date' => '2026-10-12',
            'act' => [
                'participants' => 'Mulk egasi Xodjayeva X.M.',
                'defects' => 'Hammomda suvoq yorilgan.',
                'elements' => [
                    'foundation' => ['wear' => '10'],                                  // condition from the wear
                    'walls' => ['condition' => 'Qoniqarli', 'wear' => '25,5'],
                    'utilities' => ['condition' => 'Qoniqarsiz', 'share' => '20', 'wear' => '45', 'description' => 'Elektr, gaz, suv'],
                ],
            ],
        ])->assertSessionHasNoErrors();

        $report = $this->flat->fresh()->report;
        $this->assertEquals(25.5, $report['act']['elements']['walls']['wear']);
        $this->assertEquals(20, $report['act']['elements']['utilities']['share']);
        $this->assertEquals(4, $report['act']['elements']['foundation']['share'], 'An empty share is the typical one.');
        $this->assertSame('Asosiy bino', $report['cost']['buildings'][0]['name']);

        // (4 × 10 + 26 × 25.5 + 20 × 45) / (4 + 26 + 20) = 32.06…
        $page = $this->get('/admin/real-estates/'.$this->flat->id.'/report')->assertOk();
        $page->assertSee('Mulk egasi Xodjayeva X.M.')->assertSee('Hammomda suvoq yorilgan.')
            ->assertSee('Elektr, gaz, suv')->assertSee('Qoniqarsiz')->assertSee('25,5')
            ->assertSee('Binoning jismoniy eskirishi (elementlar ulushi bo‘yicha vaznli o‘rtacha)', false)->assertSee('32,1');

        $this->put('/admin/real-estates/'.$this->flat->id.'/report', ['script' => 'latn', 'report_date' => '2026-10-12', 'act' => ['elements' => ['walls' => ['wear' => '101']]]])
            ->assertSessionHasErrors('act.elements.walls.wear');
    }

    public function test_operators_cannot_change_the_report_of_a_completed_valuation(): void
    {
        $operator = User::factory()->operator()->create();
        $this->flat->update(['status' => ValuationStatus::Completed]);

        $this->actingAs($operator)->get('/admin/real-estates/'.$this->flat->id.'/report/edit')->assertForbidden();
        $this->put('/admin/real-estates/'.$this->flat->id.'/report', ['script' => 'latn', 'report_date' => '2026-10-12'])->assertForbidden();
        $this->get('/admin/real-estates/'.$this->flat->id.'/report')->assertOk();
    }

    public function test_the_standard_texts_are_edited_by_administrators(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($this->appraiser)->get('/admin/report-texts')->assertForbidden();

        $this->actingAs($admin)->get('/admin/report-texts')->assertOk()->assertSee('Bozor qiymati');
        $this->put('/admin/report-texts', ['quality' => "- Hisobot to'g'ri.\n- **Muhim** band."])->assertRedirect();
        $this->assertSame("- Hisobot to'g'ri.\n- **Muhim** band.", ReportText::get('quality'));
        $this->assertSame(ReportText::default('terms'), ReportText::get('terms'), 'Untouched sections keep the default.');

        $this->get('/admin/real-estates/'.$this->flat->id.'/report?script=latn')->assertSee('<strong>Muhim</strong>', false);

        $this->put('/admin/report-texts', ['quality' => 'x', 'reset' => ['quality']]);
        $this->assertSame(ReportText::default('quality'), ReportText::get('quality'));
    }

    public function test_company_documents_are_appended_to_reports(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/settings/documents', ['files' => [UploadedFile::fake()->image('guvohnoma.jpg')]])->assertRedirect();
        $this->assertCount(1, json_decode(Setting::get('report_documents'), true));

        $this->actingAs($this->appraiser)->get('/admin/report-documents/0')->assertOk();
        $this->get('/admin/real-estates/'.$this->flat->id.'/report')->assertSee('/admin/report-documents/0');

        $this->actingAs($admin)->delete('/admin/settings/documents/0')->assertRedirect();
        $this->assertSame([], json_decode(Setting::get('report_documents'), true));
        $this->post('/admin/settings/documents', ['files' => [UploadedFile::fake()->create('virus.php', 10, 'image/jpeg')]])->assertSessionHasErrors('files.0');
    }

    public function test_analogue_details_photo_and_weighting_are_saved(): void
    {
        Storage::fake('local');
        $this->actingAs($this->appraiser);
        $analog = $this->flat->analogs()->first();

        $this->put('/admin/real-estates/'.$this->flat->id.'/analogs', [
            'weighting' => 'size',
            'analogs' => [$analog->id => [
                'price' => '25 000', 'currency' => 'USD', 'area' => '28,9', 'listed_at' => '2026-09-01',
                'condition' => 'Qoniqarli', 'wall_material' => 'Panel', 'adjustments' => ['bargain' => '-3', 'market' => '40'], 'included' => '1',
            ]],
        ])->assertRedirect();

        $analog->refresh();
        $this->assertSame('Panel', $analog->wall_material);
        $this->assertSame('Qoniqarli', $analog->condition);
        $this->assertSame('11814.05', $analog->usd_rate, 'The dollar is converted at the rate of the offer date.');
        $this->assertSame('size', $this->flat->fresh()->report['weighting']);

        $this->post('/admin/analogs/'.$analog->id.'/photo', ['photo' => UploadedFile::fake()->image('a.jpg', 800, 600)])->assertRedirect();
        $this->assertNotNull($path = $analog->fresh()->photo_path);
        Storage::disk('local')->assertExists($path);
        $this->get('/admin/analogs/'.$analog->id.'/photo')->assertOk();

        $this->delete('/admin/analogs/'.$analog->id.'/photo')->assertRedirect();
        Storage::disk('local')->assertMissing($path);
    }

    public function test_photos_are_only_downloaded_from_public_addresses(): void
    {
        $this->assertNull(AnalogPhoto::target('http://127.0.0.1/a.jpg'));
        $this->assertNull(AnalogPhoto::target('http://10.0.0.5/a.jpg'));
        $this->assertNull(AnalogPhoto::target('http://169.254.169.254/latest/meta-data'));
        $this->assertNull(AnalogPhoto::target('file:///etc/passwd'));
        $this->assertNull(AnalogPhoto::target('https://8.8.8.8:8443/a.jpg'));
        $this->assertSame(['https://8.8.8.8/a.jpg', '8.8.8.8'], AnalogPhoto::target('https://8.8.8.8/a.jpg'));
    }

    public function test_rates_of_a_past_date_are_taken_from_the_archive(): void
    {
        config(['fairvalue.currency.history' => true]);
        Http::fake(['cbu.uz/*' => Http::response([
            ['Ccy' => 'USD', 'Rate' => '12100.50', 'Nominal' => '1', 'Diff' => '0', 'Date' => '03.03.2026'],
        ])]);

        $this->assertSame(12100.5, app(CurrencyService::class)->rateOn('USD', '2026-03-03'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/json/all/2026-03-03/'));

        // A day already stored is not downloaded again.
        Http::fake();
        $this->assertSame(11814.05, app(CurrencyService::class)->rateOn('USD', '2026-09-01'));
        Http::assertNothingSent();
    }
}
