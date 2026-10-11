<?php

namespace Tests\Feature\Admin;

use App\Models\RealEstate;
use App\Models\SmallPart;
use App\Models\User;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RegionSeeder::class);
    }

    public function test_only_administrators_manage_users_and_settings(): void
    {
        $appraiser = User::factory()->create();
        $operator = User::factory()->operator()->create();
        $admin = User::factory()->admin()->create();

        foreach (['/admin/users', '/admin/settings', '/admin/activity', '/admin/regions'] as $url) {
            $this->actingAs($appraiser)->get($url)->assertForbidden();
            $this->actingAs($operator)->get($url)->assertForbidden();
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_operators_cannot_delete_or_edit_catalogues(): void
    {
        $operator = User::factory()->operator()->create();
        $object = RealEstate::factory()->create();
        $part = SmallPart::create(['name' => 'Klips', 'unit' => 'pcs', 'price' => 3000]);

        $this->actingAs($operator)->delete('/admin/real-estates/'.$object->id)->assertForbidden();
        $this->actingAs($operator)->post('/admin/small-parts', ['name' => 'X', 'unit' => 'pcs', 'price' => 1])->assertForbidden();
        $this->actingAs($operator)->delete('/admin/small-parts/'.$part->id)->assertForbidden();
        $this->assertNotSoftDeleted($object);
    }

    public function test_appraisers_can_delete_objects_softly(): void
    {
        $appraiser = User::factory()->create();
        $object = RealEstate::factory()->create();

        $this->actingAs($appraiser)->delete('/admin/real-estates/'.$object->id)->assertRedirect();
        $this->assertSoftDeleted($object);
    }

    public function test_administrator_cannot_lock_themselves_out(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put('/admin/users/'.$admin->id, [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'operator', 'is_active' => 0,
        ])->assertRedirect();

        $this->assertTrue($admin->fresh()->isAdmin());
        $this->assertTrue($admin->fresh()->is_active);

        $this->actingAs($admin)->delete('/admin/users/'.$admin->id)->assertSessionHas('error');
    }
}
