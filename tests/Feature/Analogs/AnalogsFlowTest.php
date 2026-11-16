<?php

namespace Tests\Feature\Analogs;

use App\Enums\ValuationStatus;
use App\Models\Analog;
use App\Models\CurrencyRate;
use App\Models\Organization;
use App\Models\RealEstate;
use App\Models\User;
use App\Reports\RealEstateReport;
use App\Services\Analogs\ListingAi;
use App\Support\ComparativeCalculator;
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
            'links' => "https://www.olx.uz/d/obyavlenie/kv-ID1.html\nhttp://169.254.169.254/latest\nhttp://localhost/x\nhttps://www.olx.uz/d/obyavlenie/kv-ID1.html",
        ])->assertRedirect()->assertSessionHas('status');

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

    public function test_links_without_https_and_with_punctuation_around_them_are_understood(): void
    {
        $this->withoutAi();
        Http::fake();

        $this->actingAs($this->user)->post($this->url(), [
            'mode' => 'links',
            'links' => "Qarang: www.olx.uz/d/obyavlenie/kv-ID7.html, (olx.uz/d/obyavlenie/kv-ID8.html)\n«https://uybor.uz/listings/123».",
        ])->assertSessionHas('status');

        $this->assertSame([
            'https://www.olx.uz/d/obyavlenie/kv-ID7.html',
            'https://olx.uz/d/obyavlenie/kv-ID8.html',
            'https://uybor.uz/listings/123',
        ], $this->flat->analogs()->orderBy('id')->pluck('url')->all());
        $this->assertTrue($this->flat->analogs()->get()->every->isPending());
        Http::assertNothingSent();

        $this->post($this->url(), ['mode' => 'links', 'links' => 'narxi 50.000 dollar'])->assertSessionHas('error');
        $this->post($this->url(), ['mode' => 'links', 'links' => 'https://uybor.uz/listings/123'])->assertSessionHas('error');
    }

    public function test_a_link_to_another_site_is_kept_for_the_text_of_its_listing_and_never_opened(): void
    {
        $this->withoutAi();
        Http::fake();
        $this->flat->update(['approaches' => ['cost', 'comparative']]);

        $this->actingAs($this->user)->withSession(['locale' => 'uz'])->post($this->url(), [
            'mode' => 'links',
            'links' => "https://t.me/uyjoy_toshkent/1234\nhttps://makler.uz/kvartira/55",
        ])->assertSessionHas('status');

        $analogs = $this->flat->analogs()->orderBy('id')->get();
        $this->assertCount(2, $analogs);
        $this->assertSame([Analog::FAILED, Analog::FAILED], $analogs->pluck('status')->all());
        $this->assertSame(['t.me', 'makler.uz'], $analogs->pluck('source')->all());
        // The page asks for the text of each listing.
        $this->withSession(['locale' => 'uz'])->get('/admin/real-estates/'.$this->flat->id)
            ->assertSee('Bu sayt avtomatik o‘qilmaydi', false)->assertSee('E’lon matnini qo‘yish', false)
            ->assertDontSee('data-analog-fill', false);
        $this->postJson('/admin/analogs/'.$analogs[0]->id.'/fill')->assertJson(['status' => 'failed']);
        Http::assertNothingSent();

        $this->post('/admin/analogs/'.$analogs[0]->id.'/text', ['text' => "2 xonali kvartira sotiladi\nNarxi: 60 000 \$\nUmumiy maydon: 60 m²\nQavat: 4\nUy qavatliligi: 9"])
            ->assertSessionHas('status');
        $this->assertTrue($analogs[0]->fresh()->isUsable());
        // The comparative value is saved by itself once the approach is applied: 60 000 × 12 500 × 0.95.
        $this->assertEqualsWithDelta(712_500_000, (float) $this->flat->fresh()->value_comparative, 1000);
    }

    public function test_a_listing_sent_from_the_browser_is_read_without_the_server_opening_the_site(): void
    {
        $this->withoutAi();
        Http::fake();
        $this->flat->update(['approaches' => ['cost', 'comparative']]);
        $url = 'https://www.olx.uz/d/obyavlenie/kv-ID9.html';
        $ld = json_encode(['@type' => 'Product', 'name' => '2-xonali kvartira', 'offers' => ['price' => '50000', 'priceCurrency' => 'USD']]);
        $send = fn (array $extra = []) => $this->post('/admin/analogs/capture', [
            'object' => $this->flat->id, 'url' => $url, 'title' => '2-xonali kvartira — OLX',
            'text' => "Umumiy maydon: 62 m²\nXonalar soni: 2\nQavat: 1\nUy qavatliligi: 9\nToshkent, Chilonzor tumani",
            'ld' => json_encode([$ld]), 'meta' => json_encode(['og:title' => '2-xonali kvartira, Chilonzor', 'onload' => 'x']),
            ...$extra,
        ]);

        // The button and the page it opens.
        $this->actingAs($this->user)->withSession(['locale' => 'uz'])->get('/admin/real-estates/'.$this->flat->id)
            ->assertSee('data-bookmarklet', false)->assertSee('javascript:(function()', false)->assertSee('data-analog-object="'.$this->flat->id.'"', false);
        $this->get('/admin/analogs/capture')->assertOk()->assertSee('data-analog-capture', false)
            ->assertSee('<option value="'.$this->flat->id.'"', false)->assertSee('Xatcho‘plar paneliga sudrang', false);

        $send()->assertRedirect('/admin/real-estates/'.$this->flat->id.'#analogs')->assertSessionHas('status');
        $analog = $this->flat->analogs()->sole();
        $this->assertTrue($analog->isUsable());
        $this->assertSame(['50000.00', 'USD', '62.00', 1, 9], [$analog->price, $analog->currency, $analog->area, $analog->floor, $analog->floors_total]);
        $this->assertSame('2-xonali kvartira', $analog->title);
        $this->assertSame([$url, 'www.olx.uz'], [$analog->url, $analog->source]);
        // The comparative value follows by itself: about 50 000 $ × 12 500 per 62 m², brought to 60 m².
        $flat = $this->flat->fresh();
        $this->assertSame(ComparativeCalculator::calculate(60.0, $flat->analogs, RealEstateReport::comparativeOptions($flat))['value'], (float) $flat->value_comparative);
        $this->assertEqualsWithDelta(605_000_000, (float) $flat->value_comparative, 2_000_000);
        Http::assertNothingSent();

        // The same listing sent again is read again, not added twice.
        $send(['ld' => json_encode([str_replace('50000', '48000', $ld)])])->assertSessionHas('status');
        $this->assertSame('48000.00', $this->flat->analogs()->sole()->price);

        // A link that could not be opened is filled in by the button.
        $failed = $this->flat->analogs()->create(['status' => Analog::FAILED, 'url' => 'https://uybor.uz/listings/77', 'note' => 'Code 403']);
        $send(['url' => 'https://uybor.uz/listings/77'])->assertSessionHas('status');
        $this->assertTrue($failed->fresh()->isUsable());
        $this->assertSame(2, $this->flat->analogs()->count());

        // Not an address of a website, or an object of another workspace: nothing is added.
        $send(['url' => 'javascript:alert(1)'])->assertSessionHas('error');
        $this->assertSame(2, $this->flat->analogs()->count());
        $bank = Organization::create(['name' => 'Bank']);
        $this->actingAs(User::factory()->create(['organization_id' => $bank->id]));
        $send(['url' => 'https://uybor.uz/listings/78'])->assertNotFound();
        $this->get('/admin/analogs/capture')->assertOk()->assertDontSee('<option value="'.$this->flat->id.'"', false);
    }

    public function test_a_page_that_was_not_read_counts_once_its_price_and_area_are_typed_in(): void
    {
        $this->withoutAi();
        $this->flat->update(['approaches' => ['cost', 'comparative']]);
        $analog = $this->flat->analogs()->create(['status' => Analog::FAILED, 'url' => 'https://www.olx.uz/d/obyavlenie/x.html', 'note' => 'Code 403']);

        $this->actingAs($this->user)->put($this->url(), ['analogs' => [$analog->id => [
            'price' => '600 000 000', 'currency' => 'UZS', 'area' => '60', 'floor' => '4', 'floors_total' => '9', 'included' => '1',
        ]]])->assertSessionHas('status');

        $analog->refresh();
        $this->assertSame(Analog::READY, $analog->status);
        $this->assertNull($analog->note);
        $this->assertTrue($analog->isUsable());
        $this->assertSame(-5.0, (float) $analog->adjustments['bargain'], 'The usual adjustments are suggested.');
        $this->assertEqualsWithDelta(570_000_000, (float) $this->flat->fresh()->value_comparative, 1000);

        // Without analogues there is no comparative value.
        $this->delete('/admin/analogs/'.$analog->id)->assertRedirect();
        $this->assertNull($this->flat->fresh()->value_comparative);
    }

    public function test_the_comparative_value_is_not_saved_while_the_approach_is_refused(): void
    {
        $this->withoutAi();
        $this->actingAs($this->user)->post($this->url(), ['mode' => 'manual', 'title' => 'Kvartira A', 'price' => '600 000 000', 'currency' => 'UZS', 'area' => '60'])
            ->assertSessionHasNoErrors();

        $this->assertNull($this->flat->fresh()->value_comparative);
    }

    public function test_a_page_that_cannot_be_opened_is_marked(): void
    {
        $this->withoutAi();
        Http::fake(['www.olx.uz/*' => Http::response('Not found', 404)]);
        $analog = $this->flat->analogs()->create(['status' => Analog::PENDING, 'url' => 'https://www.olx.uz/d/obyavlenie/gone.html']);

        $this->actingAs($this->user)->postJson('/admin/analogs/'.$analog->id.'/fill')->assertOk()->assertJson(['status' => 'failed']);
        $this->assertNotNull($analog->fresh()->note);
    }

    public function test_a_site_that_refuses_the_server_is_read_from_the_pasted_text(): void
    {
        $this->withoutAi();
        Http::fake(['www.olx.uz/*' => Http::response('Forbidden', 403)]);
        CurrencyRate::create(['code' => 'USD', 'nominal' => 1, 'rate' => 11889.95, 'diff' => 0, 'rate_date' => '2026-08-13']);
        $analog = $this->flat->analogs()->create(['status' => Analog::PENDING, 'url' => 'https://www.olx.uz/d/obyavlenie/kvartira-ID3abc.html']);

        $this->actingAs($this->user)->postJson('/admin/analogs/'.$analog->id.'/fill')->assertJson(['status' => 'failed']);
        $this->assertStringContainsString('403', $analog->fresh()->note);
        $this->withSession(['locale' => 'uz'])->get('/admin/real-estates/'.$this->flat->id)->assertSee('E’lon matnini qo‘yish', false);

        $text = "Продается 2-комнатная квартира, Turon MFY\nОпубликовано 13 августа 2026 г.\n77 000 у.е.\nДоговорная\nЭтаж: 1\nЭтажность дома: 4\n"
            ."Общая площадь: 61.52 м²\nКоличество комнат: 2\nРемонт: Евроремонт\nМестоположение\nТашкент, Мирзо-Улугбекский район\nПохожие объявления\n63 000 у.е.";
        $this->post('/admin/analogs/'.$analog->id.'/text', ['text' => $text])->assertRedirect()->assertSessionHas('status');

        $analog->refresh();
        $this->assertSame(Analog::READY, $analog->status);
        $this->assertSame('77000.00', $analog->price);
        $this->assertSame('USD', $analog->currency);
        $this->assertSame('11889.95', $analog->usd_rate, 'The dollar rate of the day the offer was published.');
        $this->assertSame('61.52', $analog->area);
        $this->assertSame([1, 4, 2], [$analog->floor, $analog->floors_total, $analog->rooms]);
        $this->assertSame('2026-08-13', $analog->listed_at->toDateString());
        $this->assertSame('Ташкент, Мирзо-Улугбекский район', $analog->location);
        $this->assertSame('Евроремонт', $analog->condition);
        $this->assertSame(-5.0, (float) $analog->adjustments['bargain'], 'Adjustments are suggested as for a read page.');
        $this->assertSame('https://www.olx.uz/d/obyavlenie/kvartira-ID3abc.html', $analog->url);

        $this->post('/admin/analogs/'.$analog->id.'/text', ['text' => 'Hech narsa yo‘q'])->assertSessionHas('error');
        $this->flat->update(['status' => ValuationStatus::Completed]);
        $this->actingAs(User::factory()->operator()->create())->post('/admin/analogs/'.$analog->id.'/text', ['text' => $text])->assertForbidden();
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

    public function test_communications_of_an_analogue_set_the_adjustment_for_amenities(): void
    {
        $this->withoutAi();
        $this->flat->update(['features' => ['electricity', 'gas', 'water', 'sewerage', 'heating', 'hot_water', 'elevator'], 'approaches' => ['comparative']]);
        $this->actingAs($this->user)->post($this->url(), ['mode' => 'manual', 'title' => 'Kvartira A', 'price' => '600 000 000', 'currency' => 'UZS', 'area' => '60'])->assertSessionHasNoErrors();
        $analog = $this->flat->analogs()->sole();
        $this->assertNull($analog->communications);

        $row = ['price' => $analog->price, 'currency' => 'UZS', 'area' => $analog->area, 'included' => '1', 'adjustments' => ['bargain' => '-5', 'amenities' => '0'], 'communications_shown' => '1'];
        $this->put($this->url(), ['analogs' => [$analog->id => [...$row, 'communications' => ['electricity', 'gas', 'water', 'sewerage', 'nonsense']]], 'weighting' => 'count10'])->assertRedirect();

        $analog->refresh();
        $this->assertSame(['electricity', 'gas', 'water', 'sewerage'], $analog->communications);
        $this->assertSame(2.0, (float) $analog->adjustments['amenities'], 'Six against four communications: +2 %.');
        $this->assertSame('count10', $this->flat->fresh()->report['weighting']);

        // Saving without marks leaves them unknown: no adjustment is added.
        $other = $this->flat->analogs()->create(['status' => Analog::READY, 'title' => 'B', 'price' => 600_000_000, 'currency' => 'UZS', 'price_uzs' => 600_000_000, 'area' => 60, 'included' => true]);
        $this->put($this->url(), ['analogs' => [$other->id => [...$row, 'adjustments' => ['amenities' => '0']]]]);
        $this->assertNull($other->fresh()->communications);
        $this->assertSame(0.0, (float) $other->fresh()->adjustments['amenities']);

        // Unchanged communications keep the amenities adjustment the appraiser typed.
        $this->put($this->url(), ['analogs' => [$analog->id => [...$row, 'adjustments' => ['amenities' => '3'], 'communications' => $analog->communications]]]);
        $this->assertSame(3.0, (float) $analog->fresh()->adjustments['amenities']);

        $this->get('/admin/real-estates/'.$this->flat->id.'/report?script=latn')->assertOk()
            ->assertSee('elektr energiya ta’minoti, gaz ta’minoti, suv ta’minoti, kanalizatsiya (4)', false)
            ->assertSee('butun o‘n foizlarga yaxlitlandi', false);
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
