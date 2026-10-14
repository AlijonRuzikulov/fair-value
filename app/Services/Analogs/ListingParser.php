<?php

namespace App\Services\Analogs;

use DOMDocument;
use DOMXPath;

/**
 * Reads a listing page without AI: schema.org data (JSON-LD), meta tags
 * and the labelled parameters the listing sites print ("Общая площадь: 65 м²",
 * "Umumiy maydon: 65 m²"). Also prepares a compact text of the page for AI.
 */
class ListingParser
{
    /** @return array<string, mixed> a listing; missing values are null */
    public function parse(string $html, string $url): array
    {
        $dom = $this->dom($html);
        $xpath = new DOMXPath($dom);
        $listing = self::empty($url);

        foreach ($this->jsonLd($xpath) as $node) {
            $listing = self::merge($listing, $this->fromSchema($node, $url));
        }

        $meta = $this->meta($xpath);
        $listing['title'] ??= self::clean($meta['og:title'] ?? $this->firstText($xpath, '//h1') ?? $this->firstText($xpath, '//title'));
        if ($listing['price'] === null && isset($meta['product:price:amount'])) {
            $listing['price'] = self::number($meta['product:price:amount']);
            $listing['currency'] ??= self::currency($meta['product:price:currency'] ?? null);
        }

        $text = $listing['title'].' '.($meta['og:description'] ?? $meta['description'] ?? '').' '.$this->text($html, 20000);

        return self::merge($listing, self::fromText($text));
    }

    /** Page text for AI: title, description, structured data, visible text and listing links. */
    public function digest(string $html, string $url, int $limit = 30000): string
    {
        $dom = $this->dom($html);
        $xpath = new DOMXPath($dom);
        $meta = $this->meta($xpath);

        $parts = ['URL: '.$url];
        if ($title = $meta['og:title'] ?? $this->firstText($xpath, '//title')) {
            $parts[] = 'Title: '.self::clean($title);
        }
        if ($description = $meta['og:description'] ?? $meta['description'] ?? null) {
            $parts[] = 'Description: '.self::clean($description);
        }
        $ld = array_map(fn ($node) => json_encode($node, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $this->jsonLd($xpath));
        if ($ld) {
            $parts[] = "Structured data:\n".mb_substr(implode("\n", $ld), 0, 6000);
        }

        $links = [];
        foreach ($xpath->query('//a[@href]') as $a) {
            $href = self::absolute(trim($a->getAttribute('href')), $url);
            $label = self::clean($a->textContent);
            if ($href && $label !== '' && PageFetcher::allowed($href) && ! isset($links[$href]) && $href !== $url) {
                $links[$href] = mb_substr($label, 0, 160).' — '.$href;
            }
            if (count($links) >= 150) {
                break;
            }
        }

        $text = $this->text($html, $limit);
        $parts[] = "Page text:\n".$text;
        if ($links) {
            $parts[] = "Links on the page:\n".implode("\n", $links);
        }

        return mb_substr(implode("\n\n", $parts), 0, $limit + 15000);
    }

    /** @return array<string, mixed> */
    public static function empty(?string $url = null): array
    {
        return [
            'url' => $url, 'title' => null, 'price' => null, 'currency' => null, 'area' => null,
            'rooms' => null, 'floor' => null, 'floors_total' => null, 'location' => null,
            'condition' => null, 'listed_at' => null,
        ];
    }

    /** Fills the gaps of $base with values from $extra. */
    public static function merge(array $base, array $extra): array
    {
        foreach ($extra as $key => $value) {
            if (($base[$key] ?? null) === null && $value !== null && $value !== '') {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    public static function currency(?string $raw): ?string
    {
        $raw = mb_strtolower(trim((string) $raw));

        return match (true) {
            $raw === '' => null,
            in_array($raw, ['usd', '$', 'у.е.', 'у.е', 'уе', 'y.e.', 'y.e', 'ye', 'u.e.', 'доллар', 'dollar'], true) => 'USD',
            in_array($raw, ['uzs', 'sum', 'сум', 'сўм', "so'm", 'so‘m', 'som', 'soʻm'], true) => 'UZS',
            default => null,
        };
    }

    /** "1 250 000,50" / "1,250,000.50" / "65.4" → float */
    public static function number(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $value = preg_replace('/[\s\x{00A0}\x{202F}]/u', '', (string) $value);
        if (! preg_match('/\d/', $value)) {
            return null;
        }
        if (preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $value)) {
            $value = str_replace(',', '', $value);
        }
        $value = str_replace(',', '.', $value);
        if (! preg_match('/\d+(\.\d+)?/', $value, $m)) {
            return null;
        }

        return (float) $m[0];
    }

    /** Labelled parameters and title patterns in Russian, Uzbek (Latin and Cyrillic). */
    public static function fromText(string $text): array
    {
        $found = [];
        $patterns = [
            'area' => [
                '/(?:Общая площадь|Umumiy maydon[i]?|Умумий майдон[и]?)\s*:?\s*(\d+(?:[.,]\d+)?)/iu',
                '/(\d+(?:[.,]\d+)?)\s*(?:м²|м2|m²|m2|кв\.?\s*м|kv\.?\s*m)/iu',
            ],
            'rooms' => [
                '/(?:Количество комнат|Xonalar soni|Хоналар сони)\s*:?\s*(\d+)/iu',
                '/(\d+)\s*[-–]?\s*(?:комн|xonali|хонали)/iu',
            ],
            'floors_total' => [
                '/(?:Этажность(?: дома)?|Uy qavatliligi|Қаватлар сони|Qavatlar soni|Уй қаватлилиги)\s*:?\s*(\d+)/iu',
                '/\b\d+\s*\/\s*(\d+)\s*(?:эт|qavat|қават)/iu',
                '/(\d+)\s*-?\s*(?:этажн|qavatli|қаватли)/iu',
            ],
            'floor' => [
                '/(?:^|[\s,;])(?:Этаж|Qavat|Қават)\s*:\s*(\d+)/iu',
                '/\b(\d+)\s*\/\s*\d+\s*(?:эт|qavat|қават)/iu',
                '/(\d+)\s*-?\s*(?:этаж(?!н)|qavat(?!li)|қават(?!ли))/iu',
            ],
        ];

        foreach ($patterns as $key => $regexes) {
            foreach ($regexes as $regex) {
                if (preg_match($regex, $text, $m)) {
                    $found[$key] = $key === 'area' ? self::number($m[1]) : (int) $m[1];
                    break;
                }
            }
        }

        if (isset($found['area']) && ($found['area'] < 5 || $found['area'] > 100000)) {
            unset($found['area']);
        }

        return $found;
    }

    private function fromSchema(array $node, string $url): array
    {
        $offer = $node['offers'] ?? [];
        if (array_is_list($offer) && $offer) {
            $offer = $offer[0];
        }
        $price = $offer['price'] ?? $offer['lowPrice'] ?? $offer['priceSpecification']['price'] ?? $node['price'] ?? null;
        $currency = $offer['priceCurrency'] ?? $offer['priceSpecification']['priceCurrency'] ?? $node['priceCurrency'] ?? null;

        $size = $node['floorSize'] ?? $node['itemOffered']['floorSize'] ?? null;
        $area = is_array($size) ? ($size['value'] ?? null) : $size;

        $address = $node['address'] ?? $node['itemOffered']['address'] ?? null;
        if (is_array($address)) {
            $address = implode(', ', array_filter([
                $address['addressRegion'] ?? null, $address['addressLocality'] ?? null, $address['streetAddress'] ?? null,
            ], fn ($v) => is_string($v) && $v !== ''));
        }

        $rooms = $node['numberOfRooms'] ?? $node['itemOffered']['numberOfRooms'] ?? null;
        $posted = $node['datePosted'] ?? $offer['availabilityStarts'] ?? null;

        return [
            'title' => isset($node['name']) && is_string($node['name']) ? self::clean($node['name']) : null,
            'price' => $price !== null ? self::number($price) : null,
            'currency' => self::currency(is_string($currency) ? $currency : null),
            'area' => $area !== null ? self::number($area) : null,
            'rooms' => is_numeric($rooms) ? (int) $rooms : null,
            'location' => is_string($address) && $address !== '' ? self::clean($address) : null,
            'listed_at' => is_string($posted) && strtotime($posted) ? date('Y-m-d', strtotime($posted)) : null,
        ];
    }

    /** @return list<array> schema.org nodes that describe something for sale */
    private function jsonLd(DOMXPath $xpath): array
    {
        $nodes = [];
        foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
            $data = json_decode(trim($script->textContent), true);
            if (! is_array($data)) {
                continue;
            }
            $stack = array_is_list($data) ? $data : [$data];
            while ($stack) {
                $node = array_shift($stack);
                if (! is_array($node)) {
                    continue;
                }
                if (isset($node['@graph']) && is_array($node['@graph'])) {
                    array_push($stack, ...$node['@graph']);
                }
                if (isset($node['offers']) || isset($node['floorSize']) || isset($node['price'])) {
                    $nodes[] = $node;
                }
            }
        }

        return $nodes;
    }

    /** @return array<string, string> */
    private function meta(DOMXPath $xpath): array
    {
        $meta = [];
        foreach ($xpath->query('//meta[@content]') as $tag) {
            $key = strtolower($tag->getAttribute('property') ?: $tag->getAttribute('name'));
            if ($key !== '' && ! isset($meta[$key])) {
                $meta[$key] = $tag->getAttribute('content');
            }
        }

        return $meta;
    }

    private function text(string $html, int $limit): string
    {
        $html = preg_replace('#<(script|style|noscript|svg|template)\b[^>]*>.*?</\1>#is', ' ', $html);
        $html = preg_replace('#<(br|/p|/div|/li|/h[1-6]|/tr|/dt|/dd|/section|/article)\b[^>]*>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
        $text = preg_replace('/\s*\n\s*/u', "\n", $text);

        return mb_substr(trim($text), 0, $limit);
    }

    private function firstText(DOMXPath $xpath, string $query): ?string
    {
        $node = $xpath->query($query)->item(0);

        return $node ? self::clean($node->textContent) : null;
    }

    private function dom(string $html): DOMDocument
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $dom;
    }

    private static function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim(preg_replace('/\s+/u', ' ', html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return $value === '' ? null : mb_substr($value, 0, 250);
    }

    public static function absolute(string $href, string $base): ?string
    {
        if ($href === '' || str_starts_with($href, '#') || preg_match('/^(javascript|mailto|tel):/i', $href)) {
            return null;
        }
        if (preg_match('#^https?://#i', $href)) {
            return strtok($href, '#');
        }
        $p = parse_url($base);
        if (! isset($p['scheme'], $p['host'])) {
            return null;
        }
        if (str_starts_with($href, '//')) {
            return $p['scheme'].':'.strtok($href, '#');
        }
        $origin = $p['scheme'].'://'.$p['host'];
        if (str_starts_with($href, '/')) {
            return $origin.strtok($href, '#');
        }
        $dir = isset($p['path']) ? preg_replace('#/[^/]*$#', '/', $p['path']) : '/';

        return $origin.$dir.strtok($href, '#');
    }
}
