<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Asset;
use App\Models\CarModel;
use App\Models\Client;
use App\Models\DamageCase;
use App\Models\Lead;
use App\Models\OtherAsset;
use App\Models\RealEstate;
use App\Models\Region;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A new installation starts empty: the directory of regions and the first
 * administrator, no sample clients, objects, requests or employees.
 */
class FreshInstallTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_installation_has_no_sample_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(Region::exists());
        $this->assertSame([config('fairvalue.admin.email')], User::pluck('email')->all());

        foreach ([Client::class, RealEstate::class, Asset::class, Vehicle::class, OtherAsset::class, DamageCase::class, Lead::class, CarModel::class, Activity::class] as $model) {
            $this->assertSame(0, $model::count(), $model);
        }
    }

    public function test_website_shows_no_invented_numbers(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/')->assertOk()->assertDontSee('class="stats"', false);
        $this->get('/about')->assertOk()->assertDontSee('stats-grid', false);
    }
}
