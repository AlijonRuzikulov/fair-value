<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\District;
use App\Models\User;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_details_appear_on_the_website(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put('/admin/settings', [
            'name' => 'Fair Value',
            'phone' => '+998 71 200-00-00',
            'address' => 'Toshkent, Amir Temur 1',
            'telegram' => 'fairvalue_bot',
            'labor_rate' => '135 000',
        ])->assertRedirect();

        $this->get('/contact')->assertSee('+998 71 200-00-00')->assertSee('Toshkent, Amir Temur 1')->assertSee('t.me/fairvalue_bot');
    }

    public function test_map_link_must_be_a_web_address(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put('/admin/settings', ['name' => 'X', 'phone' => '1', 'map_url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('map_url');
    }

    public function test_districts_in_use_are_not_deleted_and_names_are_transliterated(): void
    {
        $this->seed(RegionSeeder::class);
        $admin = User::factory()->admin()->create();
        $district = District::first();
        Client::factory()->create(['region_id' => $district->region_id, 'district_id' => $district->id]);

        $this->actingAs($admin)->delete('/admin/districts/'.$district->id)->assertSessionHas('error');
        $this->assertModelExists($district);

        $this->post('/admin/regions/'.$district->region_id.'/districts', ['name_uz' => 'Yangi Qo‘rg‘on tumani'])->assertRedirect();
        $this->assertDatabaseHas('districts', ['name_uz' => 'Yangi Qo‘rg‘on tumani', 'name_oz' => 'Янги Қўрғон тумани']);
    }

    public function test_language_switch_does_not_redirect_to_other_sites(): void
    {
        $this->get('/lang/uz', ['referer' => 'https://evil.example/phish'])->assertRedirect(route('home'));
    }
}
