<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Map tiles served from this site: each tile is downloaded once from the
 * tile server (with a User-Agent that names the system, as OpenStreetMap
 * asks of applications) and kept for a month. Browsers never call the
 * tile server themselves, so it does not block them, a key of a paid
 * provider stays on the server, and printed reports show the map.
 */
class MapTileController extends Controller
{
    private const KEEP_DAYS = 30;

    public function show(int $z, int $x, int $y): Response
    {
        abort_unless($z >= 0 && $z <= 19 && $x >= 0 && $y >= 0 && $x < 2 ** $z && $y < 2 ** $z, 404);

        $disk = Storage::disk('local');
        $path = "map-tiles/{$z}/{$x}/{$y}";
        $fresh = $disk->exists($path) && $disk->lastModified($path) > now()->subDays(self::KEEP_DAYS)->getTimestamp();

        if (! $fresh) {
            $body = $this->download($z, $x, $y);
            if ($body !== null) {
                $disk->put($path, $body);
            } elseif (! $disk->exists($path)) {
                abort(502);
            }
        }

        return response($disk->get($path), 200, [
            'Content-Type' => $disk->mimeType($path) ?: 'image/png',
            'Cache-Control' => 'private, max-age=604800',
        ]);
    }

    private function download(int $z, int $x, int $y): ?string
    {
        $url = strtr((string) config('fairvalue.map.tiles'), ['{z}' => $z, '{x}' => $x, '{y}' => $y, '{s}' => 'abc'[($x + $y) % 3]]);
        $contact = Setting::get('email') ?: parse_url((string) config('app.url'), PHP_URL_HOST);

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'FairValue/1.0 (valuation system; '.$contact.')',
                'Referer' => rtrim((string) config('app.url'), '/').'/',
            ])->timeout(10)->get($url);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        $type = (string) $response->header('Content-Type');

        return $response->successful() && str_starts_with($type, 'image/') ? $response->body() : null;
    }
}
