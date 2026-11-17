<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Whether the company's public website (home, services, about, contacts,
 * the request form) is shown. When it is not, the address opens the sign-in
 * page and the work panel; the report check (verify) stays open, as the QR
 * code of every report leads to it. Settings → "Public website", or
 * PUBLIC_SITE in .env until it is set there.
 */
final class PublicSite
{
    public static function enabled(): bool
    {
        $value = Setting::main('public_site', '');

        return $value === '' ? (bool) config('fairvalue.public_site') : $value === '1';
    }
}
