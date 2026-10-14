<?php

namespace App\Services\Analogs;

use App\Models\Analog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * The photo of an analogue shown in the appendix of the report: taken from
 * the listing (og:image) or uploaded by the appraiser. Kept on the server,
 * so the report stays complete after the listing is removed.
 */
class AnalogPhoto
{
    private const MAX_BYTES = 4_000_000;

    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /** Downloads the listing photo; failures are ignored (the photo is optional). */
    public function download(Analog $analog): bool
    {
        if (! $analog->image_url || $analog->photo_path) {
            return false;
        }

        try {
            [$url, $ip] = self::target($analog->image_url) ?? [null, null];
            if (! $url) {
                return false;
            }
            $parts = parse_url($url);
            $port = ($parts['scheme'] === 'https') ? 443 : 80;

            // The address checked above is the one connected to (no second DNS lookup).
            $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (FairValue analog photo)', 'Accept' => 'image/*'])
                ->withOptions(['allow_redirects' => false, 'curl' => [CURLOPT_RESOLVE => ["{$parts['host']}:{$port}:{$ip}"]]])
                ->timeout(10)->get($url);

            $type = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
            $body = $response->body();
            if (! $response->successful() || ! isset(self::TYPES[$type]) || strlen($body) > self::MAX_BYTES || @getimagesizefromstring($body) === false) {
                return false;
            }

            return $this->store($analog, $body, self::TYPES[$type]);
        } catch (Throwable) {
            return false;
        }
    }

    public function upload(Analog $analog, UploadedFile $file): void
    {
        $this->store($analog, (string) file_get_contents($file->getRealPath()), strtolower($file->extension() ?: 'jpg'));
    }

    public function delete(Analog $analog): void
    {
        if ($analog->photo_path) {
            Storage::disk('local')->delete($analog->photo_path);
            $analog->update(['photo_path' => null]);
        }
    }

    private function store(Analog $analog, string $body, string $extension): bool
    {
        $old = $analog->photo_path;
        $path = 'analogs/'.$analog->object_type.'/'.$analog->object_id.'/'.Str::uuid().'.'.$extension;
        Storage::disk('local')->put($path, $body);
        $analog->update(['photo_path' => $path]);
        if ($old) {
            Storage::disk('local')->delete($old);
        }

        return true;
    }

    /**
     * An http(s) address on the default port whose host resolves only to
     * public addresses, with the address to connect to; null otherwise.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function target(string $url): ?array
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && ! in_array($parts['port'], [80, 443], true))) {
            return null;
        }

        $host = strtolower($parts['host']);
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if ($ips === []) {
            return null;
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return null;
            }
        }

        return [trim($url), $ips[0]];
    }
}
