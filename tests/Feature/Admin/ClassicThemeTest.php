<?php

namespace Tests\Feature\Admin;

use App\Enums\ValuationStatus;
use App\Models\RealEstate;
use App\Models\Setting;
use App\Models\User;
use App\Support\Format;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin panel looks like the valuation portal the appraisers use (the
 * classic look, light blue or orange) unless Settings choose the modern one.
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

    public function test_the_menu_is_light_blue_unless_orange_is_chosen(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->get('/admin')->assertOk()->assertSee('class="admin theme-classic"', false);

        $this->put('/admin/settings', ['name' => 'Fair Value', 'phone' => '+998 71 200-00-00', 'admin_color' => 'green'])->assertSessionHasErrors('admin_color');
        $this->put('/admin/settings', ['name' => 'Fair Value', 'phone' => '+998 71 200-00-00', 'admin_color' => 'orange'])->assertSessionHasNoErrors();
        $this->get('/admin')->assertOk()->assertSee('class="admin theme-classic accent-orange"', false);
        $this->withSession(['locale' => 'uz'])->get('/admin/settings')->assertOk()
            ->assertSee('Menyu rangi')->assertSee('<option value="orange" selected>', false);
    }

    public function test_the_list_has_the_columns_and_filters_of_the_portal(): void
    {
        $this->seed(RegionSeeder::class);
        $this->actingAs(User::factory()->create());
        $flat = RealEstate::factory()->residential()->create([
            'status' => ValuationStatus::InProgress, 'approaches' => ['comparative'], 'purpose' => 'collateral',
            'value_comparative' => 500_000_000, 'market_value' => 500_000_000, 'useful_area' => 50, 'land_area' => 50, 'features' => [],
        ]);
        RealEstate::factory()->residential()->create(['status' => ValuationStatus::Completed]);

        $page = $this->withSession(['locale' => 'uz'])->get('/admin/real-estates?kind=residential')->assertOk();
        $page->assertSeeInOrder(['Kadastr raqami', 'Statusni tanlang', 'Viloyat', 'Tuman', 'Jami: 2', 'Qo‘shish',
            '#', 'Obyekt', 'Maydoni', 'Yondashuvlar', 'Alohida qulayliklar', 'Ilova hujjatlari', 'Status', 'Shaxslar', 'Baholovchi', 'Direktor', 'Jarayon'])
            ->assertSee('data-auto-filter', false)
            // The row of the portal: purpose, areas with the price of a square metre, approaches, the act, the buttons.
            ->assertSeeInOrder(['Garov', 'Umumiy yer maydoni (kv.m)', '1 kv.m narxi', 'Foydali maydoni (kv.m)', Format::number(10_000_000, 2),
                'Qiyosiy yondashuv', 'Muvofiqlashtirish', 'Alohida qulaylik mavjud emas', 'Akt', 'Binolar soni', 'Tasdiqlanmagan'])
            ->assertSee('href="'.route('admin.real-estates.show', $flat->id).'#documents-section"', false)
            // What the portal's list does not have.
            ->assertDontSee('name="appraiser_id"', false)->assertDontSee('name="from"', false)->assertDontSee('Excel')->assertDontSee('row-actions', false);

        $this->withSession(['locale' => 'uz'])->get('/admin/real-estates?kind=residential&status=completed')->assertOk()
            ->assertSee('<option value="completed" selected>', false);
    }
}
