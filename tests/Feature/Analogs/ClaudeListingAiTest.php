<?php

namespace Tests\Feature\Analogs;

use Anthropic\Client;
use App\Services\Analogs\ClaudeListingAi;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Tests\TestCase;

/**
 * The requests sent to the Claude API and how its answers are read,
 * with the HTTP layer replaced by canned responses.
 */
class ClaudeListingAiTest extends TestCase
{
    /** @var list<array{request: RequestInterface}> */
    private array $sent = [];

    private function ai(array $responses): ClaudeListingAi
    {
        $stack = HandlerStack::create(new MockHandler(array_map(
            fn (array $body) => new Response(200, ['Content-Type' => 'application/json'], json_encode($body)),
            $responses,
        )));
        $stack->push(Middleware::history($this->sent));

        return new ClaudeListingAi(new Client(apiKey: 'test-key', requestOptions: ['transporter' => new Guzzle(['handler' => $stack]), 'maxRetries' => 0]));
    }

    private static function message(array $content, string $stopReason = 'end_turn'): array
    {
        return [
            'id' => 'msg_test', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
            'content' => $content, 'stop_reason' => $stopReason, 'stop_sequence' => null,
            'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
        ];
    }

    private function body(int $i): array
    {
        return json_decode((string) $this->sent[$i]['request']->getBody(), true);
    }

    public function test_listings_are_extracted_with_a_json_schema(): void
    {
        $listings = ['listings' => [[
            'url' => null, 'title' => '2-xonali kvartira', 'price' => 58000, 'currency' => 'USD', 'area_m2' => 62.5,
            'rooms' => 2, 'floor' => 4, 'floors_total' => 9, 'location' => 'Chilonzor, Toshkent', 'condition' => null, 'listed_at' => '2026-10-01',
        ]]];
        $ai = $this->ai([self::message([['type' => 'text', 'text' => json_encode($listings)]])]);

        $result = $ai->extract("URL: https://www.olx.uz/d/obyavlenie/x\nPage text: ...");

        $this->assertSame(58000.0, $result[0]['price']);
        $this->assertSame('USD', $result[0]['currency']);
        $this->assertSame(62.5, $result[0]['area']);
        $this->assertSame(4, $result[0]['floor']);
        $this->assertSame('2026-10-01', $result[0]['listed_at']);

        $request = $this->sent[0]['request'];
        $body = $this->body(0);
        $this->assertStringEndsWith('/v1/messages', $request->getUri()->getPath());
        $this->assertSame('test-key', $request->getHeaderLine('x-api-key'));
        $this->assertStringContainsString('server-side-fallback-2026-07-01', $request->getHeaderLine('anthropic-beta'));
        $this->assertSame('claude-opus-5-5', $body['model']);
        $this->assertSame('default', $body['fallbacks']);
        $this->assertSame('low', $body['output_config']['effort']);
        $this->assertSame('json_schema', $body['output_config']['format']['type']);
        $this->assertFalse($body['output_config']['format']['schema']['additionalProperties']);
        $this->assertArrayNotHasKey('thinking', $body);
    }

    public function test_a_refusal_is_reported(): void
    {
        $ai = $this->ai([self::message([], 'refusal')]);

        $this->expectException(RuntimeException::class);
        $ai->extract('page');
    }

    public function test_search_uses_web_search_on_the_listing_sites_and_resumes_a_paused_turn(): void
    {
        $search = [
            ['type' => 'server_tool_use', 'id' => 'srvtoolu_1', 'name' => 'web_search', 'input' => ['query' => '2 xonali kvartira Chilonzor']],
            ['type' => 'web_search_tool_result', 'tool_use_id' => 'srvtoolu_1', 'content' => [
                ['type' => 'web_search_result', 'url' => 'https://www.olx.uz/d/obyavlenie/a-ID1.html', 'title' => 'A', 'encrypted_content' => 'x', 'page_age' => null],
                ['type' => 'web_search_result', 'url' => 'https://uybor.uz/listings/77', 'title' => 'B', 'encrypted_content' => 'y', 'page_age' => null],
            ]],
        ];
        $final = [['type' => 'text', 'text' => "Best matches:\nhttps://www.olx.uz/d/obyavlenie/b-ID2.html\nhttps://evil.example/x\nhttps://www.olx.uz/d/obyavlenie/a-ID1.html."]];
        $ai = $this->ai([self::message($search, 'pause_turn'), self::message($final)]);

        $urls = $ai->search(['type' => 'Residential property', 'area' => 62.0, 'rooms' => 2, 'district' => 'Chilonzor tumani', 'region' => 'Toshkent shahri']);

        $this->assertSame([
            'https://www.olx.uz/d/obyavlenie/b-ID2.html',
            'https://www.olx.uz/d/obyavlenie/a-ID1.html',
            'https://uybor.uz/listings/77',
        ], $urls);

        $first = $this->body(0);
        $this->assertSame('web_search_20260209', $first['tools'][0]['type']);
        $this->assertSame(config('fairvalue.analogs.domains'), $first['tools'][0]['allowed_domains']);
        $this->assertStringContainsString('Total area, m²: 62', $first['messages'][0]['content']);

        // The paused turn is sent back as it is, without an extra user message.
        $second = $this->body(1);
        $this->assertCount(2, $second['messages']);
        $this->assertSame('assistant', $second['messages'][1]['role']);
    }
}
