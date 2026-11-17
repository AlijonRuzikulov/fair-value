<?php

namespace Tests\Feature\Site;

use App\Models\RealEstate;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Without the public website the address opens the work panel; only the
 * check of reports (their QR code) stays open to everyone.
 */
class SiteSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['fairvalue.public_site' => false]);
    }

    public function test_without_the_website_the_address_opens_the_sign_in_page(): void
    {
        $this->get('/')->assertRedirect(route('admin.dashboard'));
        $this->get('/admin')->assertRedirect(route('login'));
        foreach (['/services', '/about', '/contact', '/sitemap.xml'] as $page) {
            $this->get($page)->assertNotFound();
        }
        $this->post('/request', ['name' => 'Ali', 'phone' => '+998901234567'])->assertNotFound();
        $this->withSession(['locale' => 'uz'])->get('/login')->assertOk()->assertDontSee(route('contact'));
    }

    public function test_the_report_check_stays_open_without_the_menu_of_the_website(): void
    {
        $this->seed(RegionSeeder::class);
        $report = RealEstate::factory()->create(['status' => 'completed', 'market_value' => 100]);

        $this->get('/v/'.$report->verify_code)->assertRedirect();
        $page = $this->withSession(['locale' => 'uz'])->get('/verify?code='.$report->verify_code)->assertOk();
        $page->assertSee($report->verify_code)->assertDontSee(route('services'))->assertDontSee(route('home').'#request', false)
            ->assertDontSee(route('about'));
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
