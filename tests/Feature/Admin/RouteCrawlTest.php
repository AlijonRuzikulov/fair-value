<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Analog;
use App\Models\Asset;
use App\Models\Attachment;
use App\Models\CarModel;
use App\Models\Client;
use App\Models\DamageCase;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\OtherAsset;
use App\Models\RealEstate;
use App\Models\RepairNorm;
use App\Models\SmallPart;
use App\Models\SparePart;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Tenant;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;
use Throwable;

/**
 * Opens every page of the system (every GET route, its addresses filled with
 * records of the sample data) as each kind of user, and checks that none of
 * them breaks, that signed-out visitors are sent to the sign-in page and that
 * another organisation never sees the main company's records.
 */
class RouteCrawlTest extends TestCase
{
    use RefreshDatabase;

    private const MODELS = [
        'real-estates' => RealEstate::class, 'assets' => Asset::class, 'vehicles' => Vehicle::class,
        'other-assets' => OtherAsset::class, 'damage-cases' => DamageCase::class, 'car-models' => CarModel::class,
        'spare-parts' => SparePart::class, 'small-parts' => SmallPart::class, 'repair-norms' => RepairNorm::class,
    ];

    /** Shared catalogues every workspace reads. */
    private const SHARED = ['car-models', 'spare-parts', 'small-parts', 'repair-norms'];

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response('', 503)]);
        $this->seed([DatabaseSeeder::class, DemoSeeder::class]);

        // A file and an analogue of the main company, so their addresses are opened too.
        Storage::fake('local');
        $this->actingAs(User::first());
        Attachment::keep(Client::first(), UploadedFile::fake()->image('pasport.jpg'), null, User::first()->id);
        RealEstate::first()->analogs()->create(['status' => Analog::READY, 'title' => 'Kvartira', 'price' => 1, 'area' => 1, 'photo_path' => 'analogs/1.jpg']);
        Storage::disk('local')->put('analogs/1.jpg', 'x');
        $this->app['auth']->forgetGuards();
    }

    /** @return array<int, array{uri: string, path: string, model: ?Model}> */
    private function pages(): array
    {
        $pages = [];
        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true) || str_starts_with($route->uri(), '_') || str_starts_with($route->uri(), 'storage')) {
                continue;
            }
            $uri = $route->uri();
            $model = null;
            $path = preg_replace_callback('/\{(\w+)\??\}/', function ($m) use ($uri, &$model) {
                $slug = explode('/', $uri)[1] ?? '';
                $record = match ($m[1]) {
                    'id' => (self::MODELS[$slug] ?? null) ? (self::MODELS[$slug])::withoutGlobalScopes()->first() : null,
                    'client' => Client::withoutGlobalScopes()->first(),
                    'lead' => Lead::first(),
                    'user' => User::first(),
                    'organization' => Organization::first(),
                    'analog' => Analog::withoutGlobalScopes()->first(),
                    'attachment' => Attachment::first(),
                    default => null,
                };
                if ($record) {
                    $model = $record;

                    return $record->getKey();
                }

                return match ($m[1]) {
                    'locale' => 'uz', 'slug' => 'real-estate', 'code' => 'ABCD1234', 'index' => '0',
                    'z' => '10', 'x' => '700', 'y' => '380', default => '1',
                };
            }, $uri);
            $pages[] = ['uri' => $uri, 'path' => '/'.ltrim($path, '/'), 'model' => $model];
        }

        return $pages;
    }

    private function statuses(?User $user): array
    {
        $result = [];
        foreach ($this->pages() as $page) {
            $this->app['auth']->forgetGuards();
            $this->flushSession();
            try {
                $response = $user ? $this->actingAs($user)->get($page['path']) : $this->get($page['path']);
                $status = $response->getStatusCode();
                $error = $status >= 500 ? mb_substr((string) ($response->exception?->getMessage() ?? ''), 0, 200) : '';
            } catch (Throwable $e) {
                $status = 'exception';
                $error = mb_substr($e->getMessage(), 0, 200);
            }
            $result[$page['uri']] = ['status' => $status, 'error' => $error, 'path' => $page['path'], 'model' => $page['model']];
        }

        return $result;
    }

    public function test_no_page_breaks_for_any_kind_of_user(): void
    {
        $users = [
            'admin' => User::where('role', UserRole::Admin)->whereNull('organization_id')->first(),
            'appraiser' => User::where('role', UserRole::Appraiser)->first(),
            'operator' => User::where('role', UserRole::Operator)->first(),
        ];
        $broken = [];
        foreach ($users as $name => $user) {
            foreach ($this->statuses($user) as $uri => $row) {
                $tileServerDown = str_starts_with($uri, 'admin/map/tiles') && $row['status'] === 502;
                if ($row['status'] === 'exception' || ($row['status'] >= 500 && ! $tileServerDown)) {
                    $broken[] = "$name {$row['path']} → {$row['status']} {$row['error']}";
                }
            }
        }
        $this->assertSame([], $broken, implode("\n", $broken));
    }

    public function test_signed_out_visitors_see_no_admin_page(): void
    {
        $open = [];
        foreach ($this->statuses(null) as $uri => $row) {
            if (str_starts_with($uri, 'admin') && $row['status'] !== 302) {
                $open[] = "{$row['path']} → {$row['status']}";
            }
        }
        $this->assertSame([], $open, implode("\n", $open));
    }

    public function test_another_organisation_never_opens_the_main_companys_records(): void
    {
        $organization = Organization::create(['name' => 'Boshqa baholash MChJ']);
        $user = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Admin]);
        $seen = [];
        foreach ($this->statuses($user) as $uri => $row) {
            $slug = explode('/', $uri)[1] ?? '';
            $private = $row['model'] && ! in_array($slug, self::SHARED, true)
                && ! $row['model'] instanceof Organization && ! $row['model'] instanceof User;
            if ($private && $row['status'] === 200) {
                $seen[] = "{$row['path']} → 200";
            }
        }
        $this->assertSame([], $seen, implode("\n", $seen));

        // Lists, searches and exports hold none of the main company's clients either.
        $client = Client::withoutGlobalScopes()->whereNotNull('pinfl')->whereNotNull('last_name')->first();
        foreach (['/admin/clients', '/admin/clients/export', '/admin/real-estates/export', '/admin/vehicles/export', '/admin/clients/search?q='.urlencode($client->last_name)] as $path) {
            $this->flushSession();
            $response = $this->actingAs($user)->get($path)->assertOk();
            // A download is written while it is sent, before the workspace is let go
            // (the test client lets it go first).
            Tenant::set($organization);
            $body = $response->baseResponse instanceof StreamedResponse ? $response->streamedContent() : $response->getContent();
            Tenant::clear();
            $this->assertStringNotContainsString($client->pinfl, $body, $path);
            $this->assertStringNotContainsString($client->passport_series.$client->passport_number, $body, $path);
        }
    }
}
