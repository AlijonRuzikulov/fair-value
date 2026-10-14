<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every admin page renders in every language, with sample data and on the
 * empty system a new installation starts with.
 */
class PagesTest extends TestCase
{
    use RefreshDatabase;

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
            ['/admin/real-estates/1/report'],
            ['/admin/real-estates/1/report?script=latn'],
            ['/admin/real-estates/1/report/edit'],
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
            ['/admin/activity?category=auth&q=a&ip=1&from=2026-01-01&to=2026-12-31'],
            ['/admin/system'],
            ['/admin/report-texts'],
            ['/admin/regions'],
            ['/admin/profile'],
        ];
    }

    /** Pages that do not need an existing record. */
    public static function emptyPages(): array
    {
        return array_values(array_filter(self::pages(), fn (array $page) => ! preg_match('~/\d+(/|$)|=1\b~', $page[0])));
    }

    #[DataProvider('pages')]
    public function test_page_renders(string $url): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertPageRenders($url);
    }

    #[DataProvider('emptyPages')]
    public function test_page_renders_on_an_empty_system(string $url): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertPageRenders($url);
    }

    private function assertPageRenders(string $url): void
    {
        $admin = User::where('email', config('fairvalue.admin.email'))->firstOrFail();

        foreach (array_keys(config('fairvalue.locales')) as $locale) {
            $this->actingAs($admin)
                ->withSession(['locale' => $locale])
                ->get($url)
                ->assertOk();
        }
    }
}
