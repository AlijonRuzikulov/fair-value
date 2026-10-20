<?php

namespace App\Services\Analogs;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Downloads listing pages. Only the sites in fairvalue.analogs.domains can
 * be reached, so a pasted link can never make the server call anything
 * else (internal addresses, other services).
 */
class PageFetcher
{
    private const MAX_BYTES = 5_000_000;

    private const MAX_REDIRECTS = 3;

    public static function allowed(string $url): bool
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return false;
        }
        if (isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && ! in_array($parts['port'], [80, 443], true))) {
            return false;
        }

        $host = strtolower($parts['host'] ?? '');
        foreach (config('fairvalue.analogs.domains') as $domain) {
            $domain = strtolower($domain);
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{url: string, html: string} the final address and the page */
    public function fetch(string $url): array
    {
        $url = trim($url);

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            if (! self::allowed($url)) {
                throw new InvalidArgumentException(__('Only links to these sites are accepted: :sites.', ['sites' => implode(', ', config('fairvalue.analogs.domains'))]));
            }

            // The headers a browser sends; some sites refuse requests that look like robots.
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language' => 'ru-RU,ru;q=0.9,uz;q=0.8,en;q=0.7',
                'Accept-Encoding' => 'gzip, deflate',
                'Cache-Control' => 'no-cache',
                'Upgrade-Insecure-Requests' => '1',
                'Sec-Fetch-Dest' => 'document',
                'Sec-Fetch-Mode' => 'navigate',
                'Sec-Fetch-Site' => 'none',
                'Sec-Fetch-User' => '?1',
                'sec-ch-ua' => '"Chromium";v="130", "Google Chrome";v="130", "Not?A_Brand";v="99"',
                'sec-ch-ua-mobile' => '?0',
                'sec-ch-ua-platform' => '"Windows"',
            ])->withOptions(['allow_redirects' => false, 'decode_content' => true])->timeout(15)->get($url);

            if ($response->redirect()) {
                $location = (string) $response->header('Location');
                $url = str_starts_with($location, '/') ? self::origin($url).$location : $location;

                continue;
            }

            if (in_array($response->status(), [401, 403, 429, 451], true)) {
                throw new RuntimeException(__('The site did not let the server open the page (code :code). Paste the text of the listing below.', ['code' => $response->status()]));
            }
            if (! $response->successful()) {
                throw new RuntimeException(__('The page could not be opened (code :code).', ['code' => $response->status()]));
            }

            $html = $response->body();
            if (strlen($html) > self::MAX_BYTES) {
                $html = substr($html, 0, self::MAX_BYTES);
            }

            return ['url' => $url, 'html' => $html];
        }

        throw new RuntimeException(__('The page could not be opened (too many redirects).'));
    }

    private static function origin(string $url): string
    {
        $p = parse_url($url);

        return $p['scheme'].'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '');
    }
}
