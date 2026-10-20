<?php

namespace Tests\Feature\Report;

use App\Enums\ValuationStatus;
use App\Models\Activity;
use App\Models\RealEstate;
use App\Models\User;
use App\Services\Pdf\PdfPrinter;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * The report and the contract downloaded as PDF files (printed by Chromium
 * on the server) with or without QR codes.
 */
class PdfDownloadTest extends TestCase
{
    use RefreshDatabase;

    private User $appraiser;

    private RealEstate $flat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RegionSeeder::class);
        $this->appraiser = User::factory()->create();
        $this->flat = RealEstate::factory()->residential()->create([
            'contract_number' => '668-26', 'report_number' => '20/551-26', 'market_value' => 830_177_000,
            'status' => ValuationStatus::InProgress, 'appraiser_id' => $this->appraiser->id, 'report' => null,
        ]);
    }

    public function test_the_files_are_named_after_the_numbers_and_carry_qr_codes_when_asked(): void
    {
        $this->mock(PdfPrinter::class, function (MockInterface $printer) {
            $printer->shouldReceive('available')->andReturn(true);
            $printer->shouldReceive('print')->andReturnUsing(fn (string $html) => '%PDF-1.7 '.(str_contains($html, 'data-qr=') ? 'with QR' : 'without QR'));
        });
        $this->actingAs($this->appraiser);

        $report = $this->get('/admin/real-estates/'.$this->flat->id.'/report/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame('attachment; filename=hisobot-20-551-26.pdf', $report->headers->get('Content-Disposition'));
        $this->assertSame('%PDF-1.7 with QR', $report->getContent());

        $plain = $this->get('/admin/real-estates/'.$this->flat->id.'/report/pdf?qr=0')->assertOk();
        $this->assertSame('attachment; filename=hisobot-20-551-26-qrsiz.pdf', $plain->headers->get('Content-Disposition'));
        $this->assertSame('%PDF-1.7 without QR', $plain->getContent());

        $contract = $this->get('/admin/real-estates/'.$this->flat->id.'/contract/pdf?qr=1')->assertOk();
        $this->assertSame('attachment; filename=shartnoma-668-26-qr.pdf', $contract->headers->get('Content-Disposition'));
        $this->assertSame('%PDF-1.7 with QR', $contract->getContent());
        $this->assertSame('%PDF-1.7 without QR', $this->get('/admin/real-estates/'.$this->flat->id.'/contract/pdf')->getContent());

        $this->assertSame(4, Activity::where('event', 'printed')->where('changes->format', 'pdf')->count());
    }

    public function test_without_chromium_the_buttons_open_the_page_with_the_print_dialog(): void
    {
        config(['fairvalue.pdf.chrome' => '/nonexistent/chromium']);
        $this->actingAs($this->appraiser);

        $this->get('/admin/real-estates/'.$this->flat->id.'/report/pdf?qr=0')
            ->assertRedirect(route('admin.real-estates.report', [$this->flat->id, 'qr' => 0, 'autoprint' => 1]));
        $this->get('/admin/real-estates/'.$this->flat->id.'/contract/pdf?qr=1')
            ->assertRedirect(route('admin.real-estates.contract', [$this->flat->id, 'script' => 'latn', 'qr' => 1, 'autoprint' => 1]));

        // The object page offers the four files; the report page offers the download and the QR choice.
        $this->get('/admin/real-estates/'.$this->flat->id)->assertOk()
            ->assertSee(route('admin.real-estates.report.pdf', [$this->flat->id, 'qr' => 0]), false)
            ->assertSee(route('admin.real-estates.contract.pdf', [$this->flat->id, 'qr' => 1]), false);
        $this->get('/admin/real-estates/'.$this->flat->id.'/report?qr=0')->assertOk()
            ->assertDontSee('data-qr=', false)
            ->assertSee(str_replace('/', '\/', '/admin/real-estates/'.$this->flat->id.'/report/pdf?qr=0'), false);
    }

    public function test_the_page_gets_its_styles_fonts_and_pictures_from_the_disk(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('attachments/facade.jpg', 'jpeg bytes');
        $photo = $this->flat->attachments()->create(['name' => 'facade.jpg', 'path' => 'attachments/facade.jpg', 'mime' => 'image/jpeg', 'size' => 10]);

        $html = app(PdfPrinter::class)->selfContained(
            '<link rel="stylesheet" href="'.asset('css/document-fonts.css').'">'
            .'<link rel="stylesheet" href="'.url('/').'/../.env">'
            .'<script src="'.asset('js/report.js').'"></script>'
            .'<img src="'.route('admin.attachments.show', $photo).'"><img src="'.route('admin.attachments.show', 999).'">'
        );

        $this->assertMatchesRegularExpression('#<style>[^<]*@font-face#', $html);
        $this->assertStringContainsString('url("data:font/woff2;base64,', $html);
        $this->assertStringNotContainsString('APP_KEY', $html);                 // nothing outside public/
        $this->assertStringContainsString('<script src="file://'.public_path('js/report.js').'">', $html);
        $this->assertStringContainsString('<img src="file://'.Storage::disk('local')->path('attachments/facade.jpg').'">', $html);
        $this->assertStringContainsString('<img src="data:image/gif;base64,', $html);  // a missing picture is left blank
        $this->assertStringNotContainsString(url('/'), $html);
    }

    public function test_a_real_pdf_when_chromium_is_installed(): void
    {
        if (! app(PdfPrinter::class)->available()) {
            $this->markTestSkipped('Chromium is not installed (apt install chromium, or PDF_CHROME in .env).');
        }

        $pdf = $this->actingAs($this->appraiser)->get('/admin/real-estates/'.$this->flat->id.'/contract/pdf')->assertOk()->getContent();

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(3, preg_match_all('#/Type\s*/Page\b#', $pdf));
    }
}
