<?php

namespace Tests\Feature\Analogs;

use App\Services\Analogs\ListingParser;
use App\Services\Analogs\PageFetcher;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListingReadingTest extends TestCase
{
    public static function addresses(): array
    {
        return [
            ['https://www.olx.uz/d/obyavlenie/kvartira-ID1.html', true],
            ['https://olx.uz/d/nedvizhimost/', true],
            ['http://m.uybor.uz/listings/1', true],
            ['https://www.olx.uz:443/x', true],
            ['https://olx.uz.evil.example/x', false],
            ['https://evil.example/olx.uz', false],
            ['http://127.0.0.1/admin', false],
            ['http://169.254.169.254/latest/meta-data', false],
            ['http://localhost:8000', false],
            ['ftp://olx.uz/file', false],
            ['https://user:pass@olx.uz/', false],
            ['https://olx.uz:8080/', false],
            ['javascript:alert(1)', false],
        ];
    }

    #[DataProvider('addresses')]
    public function test_only_listing_sites_can_be_fetched(string $url, bool $allowed): void
    {
        $this->assertSame($allowed, PageFetcher::allowed($url));
    }

    public function test_a_redirect_to_another_site_is_not_followed(): void
    {
        Http::fake(['www.olx.uz/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest'])]);

        $this->expectException(InvalidArgumentException::class);
        (new PageFetcher)->fetch('https://www.olx.uz/d/obyavlenie/x.html');
    }

    public function test_redirects_within_the_site_are_followed(): void
    {
        Http::fake([
            'www.olx.uz/old' => Http::response('', 301, ['Location' => '/new']),
            'www.olx.uz/new' => Http::response('<html>ok</html>'),
        ]);

        $page = (new PageFetcher)->fetch('https://www.olx.uz/old');

        $this->assertSame('https://www.olx.uz/new', $page['url']);
        $this->assertStringContainsString('ok', $page['html']);
    }

    public function test_listing_is_read_from_structured_data_and_parameters(): void
    {
        $html = <<<'HTML'
            <html><head>
            <title>2-xonali kvartira — OLX.uz</title>
            <meta property="og:title" content="Продается 2-комнатная квартира, Чиланзор 9">
            <script type="application/ld+json">{"@context":"https://schema.org","@type":"Product","name":"Продается 2-комнатная квартира, Чиланзор 9",
              "offers":{"@type":"Offer","price":"58000","priceCurrency":"USD"},
              "address":{"addressLocality":"Ташкент","streetAddress":"Чиланзорский район"}}</script>
            </head><body>
            <h1>Продается 2-комнатная квартира</h1>
            <ul><li>Общая площадь: 62 м²</li><li>Количество комнат: 2</li><li>Этаж: 4</li><li>Этажность дома: 9</li></ul>
            <a href="/d/obyavlenie/other-ID2.html">Другая квартира</a>
            <a href="https://evil.example/x">Чужой сайт</a>
            </body></html>
            HTML;

        $parser = new ListingParser;
        $listing = $parser->parse($html, 'https://www.olx.uz/d/obyavlenie/kv-ID1.html');

        $this->assertSame(58000.0, $listing['price']);
        $this->assertSame('USD', $listing['currency']);
        $this->assertSame(62.0, $listing['area']);
        $this->assertSame(2, $listing['rooms']);
        $this->assertSame(4, $listing['floor']);
        $this->assertSame(9, $listing['floors_total']);
        $this->assertSame('Ташкент, Чиланзорский район', $listing['location']);

        $digest = $parser->digest($html, 'https://www.olx.uz/d/obyavlenie/kv-ID1.html');
        $this->assertStringContainsString('https://www.olx.uz/d/obyavlenie/other-ID2.html', $digest);
        $this->assertStringNotContainsString('evil.example', $digest);
        $this->assertStringContainsString('Общая площадь: 62', $digest);
    }

    public function test_uzbek_labels_and_numbers_are_understood(): void
    {
        $listing = ListingParser::fromText('Umumiy maydon: 74,5 m² · Xonalar soni: 3 · Qavat: 2 · Uy qavatliligi: 5');

        $this->assertSame(74.5, $listing['area']);
        $this->assertSame(3, $listing['rooms']);
        $this->assertSame(2, $listing['floor']);
        $this->assertSame(5, $listing['floors_total']);

        $this->assertSame('USD', ListingParser::currency('у.е.'));
        $this->assertSame('UZS', ListingParser::currency("so'm"));
        $this->assertSame(1250000.5, ListingParser::number('1 250 000,50'));
    }
}
