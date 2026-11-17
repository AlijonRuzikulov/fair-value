<?php

namespace Tests\Feature\Site;

use App\Models\RealEstate;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Without the public website nothing is open but the sign-in page and the
 * work panel: not even the check of reports, so reports and contracts carry
 * no QR code.
 */
class SiteSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['fairvalue.public_site' => false]);
    }

    public function test_without_the_website_every_address_leads_to_the_sign_in_page(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
        foreach (['/', '/services', '/services/real-estate', '/about', '/contact', '/sitemap.xml', '/verify', '/verify?code=FV-AAAA-BBBB', '/v/FV-AAAA-BBBB'] as $page) {
            $this->get($page)->assertRedirect(route('admin.dashboard'));
        }
        $this->post('/request', ['name' => 'Ali', 'phone' => '+998901234567'])->assertNotFound();
        $this->withSession(['locale' => 'uz'])->get('/login')->assertOk()->assertDontSee(route('contact'));
    }

    public function test_reports_and_contracts_carry_no_qr_code_without_the_website(): void
    {
        $this->seed(RegionSeeder::class);
        $report = RealEstate::factory()->create(['status' => 'completed', 'market_value' => 100, 'contract_number' => '5-26']);
        $this->actingAs(User::factory()->admin()->create())->withSession(['locale' => 'uz']);

        $this->get('/admin/real-estates/'.$report->id.'/report?qr=1')->assertOk()->assertDontSee('data-qr', false)->assertDontSee('QR kodlar bilan');
        $this->get('/admin/real-estates/'.$report->id.'/contract?qr=1')->assertOk()->assertDontSee('data-qr', false);
        $this->get('/admin/real-estates/'.$report->id.'/print')->assertOk()->assertDontSee('data-qr', false);
        $this->get('/admin/real-estates/'.$report->id)->assertOk()->assertDontSee('qr\' => 1', false)
            ->assertDontSee(route('admin.real-estates.report.pdf', $report->id).'"', false);

        // With the website the QR codes come back.
        Setting::put(['public_site' => '1']);
        $this->get('/admin/real-estates/'.$report->id.'/report')->assertSee('data-qr', false);
        $this->get('/v/'.$report->verify_code)->assertRedirect(route('verify', ['code' => $report->verify_code]));
    }

    public function test_the_panel_has_no_link_to_the_website_and_it_is_switched_on_in_the_settings(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->withSession(['locale' => 'uz']);

        $page = $this->get('/admin/real-estates?kind=residential')->assertOk();
        $page->assertDontSee('class="sidebar-site"', false)->assertDontSee(route('admin.leads.index'));
        $this->get('/admin/settings')->assertOk()->assertSee('Ochiq sayt')->assertSee('<option value="0" selected>', false);

        Setting::put(['public_site' => '1']);
        $this->get('/')->assertOk();
        $this->get('/admin/real-estates?kind=residential')->assertSee('class="sidebar-site"', false)->assertSee(route('admin.leads.index'));
    }
}
