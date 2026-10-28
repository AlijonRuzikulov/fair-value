<?php

namespace Tests\Feature\Admin;

use App\Enums\ValuationStatus;
use App\Models\RealEstate;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin panel looks like the valuation portal the appraisers use (the
 * classic look) unless Settings choose the modern one.
 */
class ClassicThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_classic_look_is_the_default_and_can_be_switched_off(): void
    {
        $this->seed(RegionSeeder::class);
        $this->actingAs(User::factory()->create());

        $this->get('/admin/real-estates?kind=residential')->assertOk()
            ->assertSee('class="admin theme-classic"', false)->assertSee('css/admin-classic.css', false)
            ->assertSee('<span class="nav-mini" aria-hidden="true">P</span>', false);

        Setting::put(['admin_theme' => 'modern']);
        $this->get('/admin/real-estates?kind=residential')->assertOk()
            ->assertDontSee('admin-classic.css', false)->assertSee('class="admin"', false);
    }

    public function test_the_list_has_the_columns_and_filters_of_the_portal(): void
    {
        $this->seed(RegionSeeder::class);
        $this->actingAs(User::factory()->create());
        RealEstate::factory()->residential()->create(['status' => ValuationStatus::InProgress, 'approaches' => ['comparative']]);
        RealEstate::factory()->residential()->create(['status' => ValuationStatus::Completed]);

        $page = $this->withSession(['locale' => 'uz'])->get('/admin/real-estates?kind=residential')->assertOk();
        $page->assertSeeInOrder(['Maydoni', 'Yondashuvlar', 'Alohida qulayliklar', 'Ilova hujjatlari', 'Shaxslar'])
            ->assertSee('Statusni tanlang')->assertSee('Barchasi (2)');

        $this->withSession(['locale' => 'uz'])->get('/admin/real-estates?kind=residential&status=completed')->assertOk()
            ->assertSee('<option value="completed" selected>', false);
    }
}
