<?php

namespace Tests\Feature\Analogs;

use App\Enums\ValuationStatus;
use App\Models\Analog;
use App\Models\CurrencyRate;
use App\Models\RealEstate;
use App\Models\User;
use App\Services\Analogs\ListingAi;
use App\Support\Format;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnalogsFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private RealEstate $flat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RegionSeeder::class);
        $this->user = User::factory()->create();
        $this->flat = RealEstate::factory()->residential()->create([
            'total_area' => 60, 'rooms' => 2, 'floor' => 4, 'floors_total' => 9,
            'status' => ValuationStatus::InProgress, 'approaches' => ['cost'], 'value_comparative' => null,
        ]);
        CurrencyRate::create(['code' => 'USD', 'nominal' => 1, 'rate' => 12500, 'diff' => 0, 'rate_date' => now()->toDateString()]);
    }

    private function withoutAi(): void
    {
        $this->app->instance(ListingAi::class, new class implements ListingAi
        {
            public function available(): bool
            {
                return false;
            }

            public function extract(string $digest): array
            {
                return [];
            }

            public function search(array $subject): array
            {
                return [];
            }
        });
    }

    private function fakeAi(array $extracted = [], array $found = []): object
    {
        $ai = new class($extracted, $found) implements ListingAi
        {
            public array $subjects = [];

            public function __construct(private array $extracted, private array $found) {}

            public function available(): bool
            {
                return true;
            }

            public function extract(string $digest): array
            {
                return $this->extracted;
            }

            public function search(array $subject): array
            {
                $this->subjects[] = $subject;

                return $this->found;
            }
        };
        $this->app->instance(ListingAi::class, $ai);

        return $ai;
    }

    private static function listingPage(string $price = '50000', string $currency = 'USD', string $area = '62', int $floor = 1): string
    {
        return '<html><head><script type="application/ld+json">{"@type":"Product","name":"2-xonali kvartira",'
            .'"offers":{"price":"'.$price.'","priceCurrency":"'.$currency.'"}}</script></head>'
            .'<body><p>Umumiy maydon: '.$area.' m²</p><p>Xonalar soni: 2</p><p>Qavat: '.$floor.'</p><p>Uy qavatliligi: 9</p></body></html>';
    }

    private function url(string $suffix = ''): string
    {
        return '/admin/real-estates/'.$this->flat->id.'/analogs'.$suffix;
    }

    public function test_pasted_links_are_saved_and_read_one_by_one(): void
    {
        $this->withoutAi();
        Http::fake(['www.olx.uz/*' => Http::response(self::listingPage())]);

        $this->actingAs($this->user)->post($this->url(), [
            'mode' => 'links',
            'links' => "https://www.olx.uz/d/obyavlenie/kv-ID1.html\nhttp://169.254.169.254/latest\nhttps://www.olx.uz/d/obyavlenie/kv-ID1.html",
        ])->assertRedirect()->assertSessionHas('error');

        $analog = $this->flat->analogs()->sole();
        $this->assertTrue($analog->isPending());

        $this->get('/admin/real-estates/'.$this->flat->id)->assertOk()->assertSee('data-analog-fill', false);

        $this->postJson('/admin/analogs/'.$analog->id.'/fill')->assertOk()->assertJson(['status' => 'ready']);

        $analog->refresh();
        $this->assertSame('50000.00', $analog->price);
        $this->assertSame('USD', $analog->currency);
        $this->assertSame('625000000.00', $analog->price_uzs);
        $this->assertSame('62.00', $analog->area);
        $this->assertSame(1, $analog->floor);
        $this->assertSame(-5.0, (float) $analog->adjustments['bargain']);
        $this->assertSame(5.0, (float) $analog->adjustments['floor'], 'First floor against the 4th.');
        Http::assertSentCount(1);
    }

    public function test_a_page_that_cannot_be_opened_is_marked(): void
    {
        $this->withoutAi();
        Http::fake(['www.olx.uz/*' => Http::response('Not found', 404)]);
        $analog = $this->flat->analogs()->create(['status' => Analog::PENDING, 'url' => 'https://www.olx.uz/d/obyavlenie/gone.html']);

        $this->actingAs($this->user)->postJson('/admin/analogs/'.$analog->id.'/fill')->assertOk()->assertJson(['status' => 'failed']);
        $this->assertNotNull($analog->fresh()->note);
    }

    public function test_a_page_of_search_results_adds_the_closest_offers(): void
    {
        $listing = fn (string $id, float $area, int $rooms, float $price) => [
            'url' => "https://www.olx.uz/d/obyavlenie/{$id}.html", 'title' => "Kvartira {$id}", 'price' => $price, 'currency' => 'UZS',
            'area' => $area, 'rooms' => $rooms, 'floor' => 3, 'floors_total' => 9, 'location' => 'Chilonzor', 'condition' => null, 'listed_at' => null,
        ];
        $this->fakeAi(extracted: [
            $listing('far', 150, 5, 1_500_000_000),
            $listing('close', 61, 2, 600_000_000),
            $listing('near', 55, 2, 560_000_000),
            $listing('rent', 60, 2, 0),
            [...$listing('trap', 90, 3, 910_000_000), 'url' => 'javascript:alert(1)'],
        ]);
        Http::fake(['www.olx.uz/*' => Http::response('<html><body>search results</body></html>')]);
        $page = $this->flat->analogs()->create(['status' => Analog::PENDING, 'url' => 'https://www.olx.uz/nedvizhimost/kvartiry/?search=1']);

        $this->actingAs($this->user)->postJson('/admin/analogs/'.$page->id.'/fill')->assertOk();

        $urls = $this->flat->analogs()->pluck('url')->all();
        $this->assertSame('https://www.olx.uz/d/obyavlenie/close.html', $urls[0], 'The closest offer takes the place of the search page.');
        $this->assertContains('https://www.olx.uz/d/obyavlenie/near.html', $urls);
        $this->assertNotContains('https://www.olx.uz/d/obyavlenie/rent.html', $urls, 'Offers without a price are skipped.');
        $this->assertSame(4, $this->flat->analogs()->count());
        $this->assertFalse($this->flat->analogs()->where('url', 'like', 'javascript:%')->exists(), 'Only listing-site addresses are kept.');
    }

    public function test_automatic_search_saves_the_links_it_finds(): void
    {
        $ai = $this->fakeAi(found: ['https://www.olx.uz/d/obyavlenie/a.html', 'https://uybor.uz/listings/2']);

        $this->actingAs($this->user)->post($this->url(), ['mode' => 'search'])->assertRedirect()->assertSessionHas('status');

        $this->assertSame(2, $this->flat->analogs()->where('status', Analog::PENDING)->count());
        $this->assertSame(60.0, $ai->subjects[0]['area']);
        $this->assertSame(2, $ai->subjects[0]['rooms']);
    }

    public function test_automatic_search_without_an_ai_key_explains_what_is_needed(): void
    {
        $this->withoutAi();

        $this->actingAs($this->user)->post($this->url(), ['mode' => 'search'])->assertSessionHas('error');
        $this->assertSame(0, $this->flat->analogs()->count());
    }

    public function test_manual_offers_adjustments_and_the_result_are_saved(): void
    {
        $this->withoutAi();
        $this->actingAs($this->user);

        $this->post($this->url(), ['mode' => 'manual', 'title' => 'Bad', 'url' => 'javascript:alert(1)', 'price' => '1', 'currency' => 'UZS', 'area' => '1'])->assertSessionHasErrors('url');
        $this->post($this->url(), ['mode' => 'manual', 'title' => 'Kvartira A', 'price' => '600 000 000', 'currency' => 'UZS', 'area' => '60', 'floor' => 4, 'floors_total' => 9])->assertSessionHasNoErrors();
        $this->post($this->url(), ['mode' => 'manual', 'title' => 'Kvartira B', 'price' => '52 000', 'currency' => 'USD', 'area' => '60', 'floor' => 5, 'floors_total' => 9])->assertSessionHasNoErrors();
        [$a, $b] = $this->flat->analogs()->get()->all();
        $this->assertSame('650000000.00', $b->price_uzs);

        $adjust = fn (Analog $analog, array $values, bool $included = true) => [
            'price' => $analog->price, 'currency' => $analog->currency, 'area' => $analog->area,
            'floor' => $analog->floor, 'floors_total' => $analog->floors_total,
            'adjustments' => $values, 'included' => $included ? '1' : null,
        ];
        $this->put($this->url(), ['analogs' => [
            $a->id => $adjust($a, ['bargain' => '-5', 'condition' => '10']),
            $b->id => $adjust($b, ['bargain' => '-5']),
        ]])->assertRedirect();
        $this->assertSame(10.0, (float) $a->fresh()->adjustments['condition']);

        $this->post($this->url('/apply'))->assertRedirect()->assertSessionHas('status');

        $flat = $this->flat->fresh();
        // A: 10 000 000 × 0.95 × 1.10 = 10 450 000; B: 10 833 333 × 0.95 = 10 291 667.
        // One adjustment besides bargaining or none: equal weights.
        $expected = round((10_450_000 + 10_291_666.67) / 2 * 60, -3);
        $this->assertEqualsWithDelta($expected, (float) $flat->value_comparative, 1000);
        $this->assertContains('comparative', $flat->approaches);
        $this->assertContains('cost', $flat->approaches);
    }

    public function test_choosing_the_comparative_approach_starts_the_search(): void
    {
        $this->fakeAi(found: []);
        $payload = [
            'kind' => 'residential', 'name' => $this->flat->name, 'total_area' => '60', 'status' => 'in_progress',
            'approaches' => ['cost', 'comparative'], 'persons' => [],
        ];

        $this->actingAs($this->user)->put('/admin/real-estates/'.$this->flat->id, $payload)
            ->assertRedirect('/admin/real-estates/'.$this->flat->id)
            ->assertSessionHas('find_analogs', true);
        $this->get('/admin/real-estates/'.$this->flat->id)->assertSee('data-auto-submit', false);

        // Once there are analogues, saving does not search again.
        $this->flat->analogs()->create(['status' => Analog::READY, 'title' => 'X']);
        $this->put('/admin/real-estates/'.$this->flat->id, $payload)->assertSessionHas('find_analogs', false);

        // Without the comparative approach there is nothing to search for.
        $this->flat->analogs()->delete();
        $this->put('/admin/real-estates/'.$this->flat->id, [...$payload, 'approaches' => ['cost']])->assertSessionHas('find_analogs', false);
    }

    public function test_operators_cannot_change_analogues_of_a_completed_report(): void
    {
        $this->withoutAi();
        $this->flat->update(['status' => ValuationStatus::Completed]);
        $analog = $this->flat->analogs()->create(['status' => Analog::READY, 'title' => 'X', 'price' => 1, 'currency' => 'UZS', 'price_uzs' => 1, 'area' => 1]);
        $operator = User::factory()->operator()->create();

        $this->actingAs($operator)->post($this->url(), ['mode' => 'links', 'links' => 'https://www.olx.uz/d/obyavlenie/x.html'])->assertForbidden();
        $this->post($this->url('/apply'))->assertForbidden();
        $this->delete('/admin/analogs/'.$analog->id)->assertForbidden();
        $this->get('/admin/real-estates/'.$this->flat->id)->assertOk()->assertDontSee('mode" value="links', false);
    }

    public function test_object_page_shows_analogues_and_the_result(): void
    {
        $this->withoutAi();
        $this->flat->analogs()->create(['status' => Analog::READY, 'title' => 'Kvartira Chilonzor', 'url' => 'https://www.olx.uz/d/obyavlenie/a.html', 'price' => 600_000_000, 'currency' => 'UZS', 'price_uzs' => 600_000_000, 'area' => 60, 'adjustments' => ['bargain' => -5], 'included' => true]);

        foreach (['oz', 'uz', 'ru'] as $locale) {
            $this->actingAs($this->user)->withSession(['locale' => $locale])
                ->get('/admin/real-estates/'.$this->flat->id)
                ->assertOk()
                ->assertSee('Kvartira Chilonzor')
                ->assertSee(Format::money(570_000_000));
        }
    }
}
