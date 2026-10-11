<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every admin page renders with demo data in every language.
 */
class PagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public static function pages(): array
    {
        return [
            ['/admin'],
            ['/admin/clients?type=individual'],
            ['/admin/clients?type=legal'],
            ['/admin/clients/create?type=individual'],
            ['/admin/clients/create?type=legal'],
            ['/admin/clients/1'],
            ['/admin/clients/1/edit'],
            ['/admin/real-estates?kind=nonresidential'],
            ['/admin/real-estates?kind=residential&status=completed'],
            ['/admin/real-estates/create?kind=residential'],
            ['/admin/real-estates/1'],
            ['/admin/real-estates/1/edit'],
            ['/admin/real-estates/1/print'],
            ['/admin/assets'],
            ['/admin/assets/create'],
            ['/admin/assets/1'],
            ['/admin/vehicles'],
            ['/admin/vehicles/create?client=1'],
            ['/admin/vehicles/1/edit'],
            ['/admin/other-assets'],
            ['/admin/other-assets/1'],
            ['/admin/damage-cases'],
            ['/admin/damage-cases/create'],
            ['/admin/damage-cases/1'],
            ['/admin/damage-cases/1/edit'],
            ['/admin/damage-cases/1/print'],
            ['/admin/car-models'],
            ['/admin/car-models/create'],
            ['/admin/spare-parts?car_model_id=1'],
            ['/admin/spare-parts/1/edit'],
            ['/admin/small-parts'],
            ['/admin/repair-norms'],
            ['/admin/repair-norms/create'],
            ['/admin/tools'],
            ['/admin/leads'],
            ['/admin/leads/1'],
            ['/admin/users'],
            ['/admin/users/create'],
            ['/admin/settings'],
            ['/admin/activity'],
            ['/admin/regions'],
            ['/admin/profile'],
        ];
    }

    #[DataProvider('pages')]
    public function test_page_renders(string $url): void
    {
        $admin = User::where('email', 'demo@fairvalue.uz')->first();

        foreach (array_keys(config('fairvalue.locales')) as $locale) {
            $this->actingAs($admin)
                ->withSession(['locale' => $locale])
                ->get($url)
                ->assertOk();
        }
    }
}
