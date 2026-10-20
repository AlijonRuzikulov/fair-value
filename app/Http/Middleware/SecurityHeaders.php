<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser-side protection: scripts only from this site (inline scripts
 * need the per-request nonce), no framing by other sites, no MIME sniffing.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            // Geolocation: the "my location" button on the map of an object.
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(self), payment=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        // Laravel's debug error page uses inline scripts of its own.
        if (! config('app.debug')) {
            $headers['Content-Security-Policy'] = implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'nonce-{$nonce}'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data:".self::tileOrigin(),
                "font-src 'self'",
                "connect-src 'self'",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'self'",
            ]);
        }

        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    /** The map tile server, which serves images to the pages with a map. */
    private static function tileOrigin(): string
    {
        $parts = parse_url((string) config('fairvalue.map.tiles'));

        return isset($parts['scheme'], $parts['host']) ? ' '.$parts['scheme'].'://'.str_replace(['{s}', '{', '}'], ['*', '', ''], $parts['host']) : '';
    }
}
