<?php

namespace App\Services\Analogs;

use Anthropic\Client;
use RuntimeException;

/**
 * ListingAi on Claude: structured extraction from listing pages and web
 * search restricted to the listing sites.
 */
class ClaudeListingAi implements ListingAi
{
    // Routes a declined request to a substitute model on the server side.
    private const BETAS = ['server-side-fallback-2026-07-01'];

    private const EXTRACT_SYSTEM = <<<'TXT'
        You read real-estate sale listings from Uzbek listing sites (OLX.uz, Uybor.uz and similar) for a licensed appraiser.
        The page text is given in Uzbek (Latin or Cyrillic) or Russian. Return every property offered FOR SALE on the page:
        one item for a single listing page, every listing card for a page of search results. Skip rentals ("аренда", "ijara", "сдается").
        Copy values exactly as the page states them; use null when a value is not on the page. Never estimate or invent values.
        price: the asking price as a number; currency: "USD" for $, у.е., y.e. or USD, "UZS" for сум/so'm.
        area_m2: total area in square metres (not living or kitchen area); for land plots in sotix (сотка) multiply by 100.
        url: the address of the listing itself (from the links list for search results), or null if it is the page itself.
        location: district and city as written, e.g. "Chilonzor tumani, Toshkent". listed_at: YYYY-MM-DD or null.
        condition: the repair state if stated (e.g. "euro repair", "needs repair"), else null.
        wall_material: the building's wall material if stated (e.g. "brick", "panel", "monolith"), else null.
        TXT;

    private const SEARCH_SYSTEM = <<<'TXT'
        You help a licensed appraiser in Uzbekistan find comparable properties currently offered for sale.
        Use web search to find individual sale listings on the allowed sites that match the object as closely as possible:
        same city and district (or a neighbouring one), the same type of property, similar area and number of rooms.
        Search in Uzbek and in Russian. Prefer pages of single listings over category pages, and recent listings over old ones.
        Finish with the addresses of the best listings, one URL per line, the closest matches first. Never make up an address.
        TXT;

    public function __construct(private ?Client $client = null) {}

    public function available(): bool
    {
        return $this->client !== null || filled(config('services.anthropic.key'));
    }

    public function extract(string $digest): array
    {
        $message = $this->client()->beta->messages->create(
            model: config('services.anthropic.model'),
            maxTokens: 16000,
            system: self::EXTRACT_SYSTEM,
            messages: [['role' => 'user', 'content' => $digest]],
            outputConfig: [
                'effort' => 'low',
                'format' => ['type' => 'json_schema', 'schema' => self::listingSchema()],
            ],
            fallbacks: 'default',
            betas: self::BETAS,
        );

        $this->ensureFinished($message->stopReason);

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $data = json_decode($block->text, true);

                return array_map(fn (array $item) => self::normalise($item), $data['listings'] ?? []);
            }
        }

        return [];
    }

    public function search(array $subject): array
    {
        $domains = config('fairvalue.analogs.domains');
        $messages = [['role' => 'user', 'content' => self::describe($subject)]];
        $found = [];
        $listed = [];

        // A server-side search loop can pause; it resumes from the same turn.
        for ($turn = 0; $turn < 3; $turn++) {
            $message = $this->client()->beta->messages->create(
                model: config('services.anthropic.model'),
                maxTokens: 16000,
                system: self::SEARCH_SYSTEM,
                messages: $messages,
                tools: [[
                    'type' => 'web_search_20260209',
                    'name' => 'web_search',
                    'maxUses' => 5,
                    'allowedDomains' => $domains,
                    'userLocation' => ['type' => 'approximate', 'country' => 'UZ', 'timezone' => 'Asia/Tashkent'],
                ]],
                outputConfig: ['effort' => 'low'],
                fallbacks: 'default',
                betas: self::BETAS,
            );

            foreach ($message->content as $block) {
                if ($block->type === 'web_search_tool_result' && is_array($block->content)) {
                    foreach ($block->content as $result) {
                        $found[] = $result->url;
                    }
                }
                if ($block->type === 'text' && preg_match_all('#https?://[^\s<>"\')\]]+#u', $block->text, $m)) {
                    array_push($listed, ...$m[0]);
                }
            }

            if ($message->stopReason !== 'pause_turn') {
                $this->ensureFinished($message->stopReason, allowMaxTokens: true);
                break;
            }
            $messages[] = ['role' => 'assistant', 'content' => $message->content];
        }

        // The model's own ranking first, then the other search results.
        $urls = array_values(array_unique(array_map(
            fn ($url) => rtrim($url, '.,;'),
            [...$listed, ...$found],
        )));

        return array_values(array_filter($urls, fn ($url) => PageFetcher::allowed($url)));
    }

    /** The object in words, for the search request. */
    public static function describe(array $subject): string
    {
        $lines = ['Find comparable sale listings for this property:'];
        foreach ([
            'type' => 'Type', 'title' => 'Name', 'area' => 'Total area, m²', 'rooms' => 'Rooms',
            'floor' => 'Floor', 'floors_total' => 'Floors in the building', 'year_built' => 'Year built',
            'condition' => 'Condition', 'region' => 'Region', 'district' => 'District / city', 'address' => 'Address',
        ] as $key => $label) {
            if (filled($subject[$key] ?? null)) {
                $lines[] = "- {$label}: {$subject[$key]}";
            }
        }
        $lines[] = 'Allowed sites: '.implode(', ', config('fairvalue.analogs.domains')).'.';

        return implode("\n", $lines);
    }

    private function ensureFinished(?string $stopReason, bool $allowMaxTokens = false): void
    {
        if ($stopReason === 'refusal') {
            throw new RuntimeException(__('The AI service declined the request.'));
        }
        if ($stopReason === 'max_tokens' && ! $allowMaxTokens) {
            throw new RuntimeException(__('The page is too long to be read.'));
        }
    }

    private function client(): Client
    {
        return $this->client ??= new Client(
            apiKey: (string) config('services.anthropic.key'),
            requestOptions: ['timeout' => 110, 'maxRetries' => 1],
        );
    }

    private static function listingSchema(): array
    {
        $nullable = fn (string $type) => ['anyOf' => [['type' => $type], ['type' => 'null']]];

        return [
            'type' => 'object',
            'properties' => [
                'listings' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'url' => $nullable('string'),
                            'title' => $nullable('string'),
                            'price' => $nullable('number'),
                            'currency' => ['anyOf' => [['type' => 'string', 'enum' => ['USD', 'UZS']], ['type' => 'null']]],
                            'area_m2' => $nullable('number'),
                            'rooms' => $nullable('integer'),
                            'floor' => $nullable('integer'),
                            'floors_total' => $nullable('integer'),
                            'location' => $nullable('string'),
                            'condition' => $nullable('string'),
                            'wall_material' => $nullable('string'),
                            'listed_at' => $nullable('string'),
                        ],
                        'required' => ['url', 'title', 'price', 'currency', 'area_m2', 'rooms', 'floor', 'floors_total', 'location', 'condition', 'wall_material', 'listed_at'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['listings'],
            'additionalProperties' => false,
        ];
    }

    private static function normalise(array $item): array
    {
        $listing = ListingParser::empty($item['url'] ?? null);
        $listing['title'] = isset($item['title']) ? mb_substr(trim($item['title']), 0, 250) : null;
        $listing['price'] = isset($item['price']) ? (float) $item['price'] : null;
        $listing['currency'] = ListingParser::currency($item['currency'] ?? null);
        $listing['area'] = isset($item['area_m2']) ? (float) $item['area_m2'] : null;
        foreach (['rooms', 'floor', 'floors_total'] as $key) {
            $listing[$key] = isset($item[$key]) ? (int) $item[$key] : null;
        }
        $listing['location'] = isset($item['location']) ? mb_substr(trim($item['location']), 0, 250) : null;
        $listing['condition'] = isset($item['condition']) ? mb_substr(trim($item['condition']), 0, 250) : null;
        $listing['wall_material'] = isset($item['wall_material']) ? mb_substr(trim($item['wall_material']), 0, 250) : null;
        $listing['listed_at'] = isset($item['listed_at']) && strtotime($item['listed_at']) ? date('Y-m-d', strtotime($item['listed_at'])) : null;

        return $listing;
    }
}
