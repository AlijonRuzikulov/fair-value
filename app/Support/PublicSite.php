<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Whether the company's public website (home, services, about, contacts,
 * the request form and the check of reports by their QR code) is shown.
 * When it is not, nothing is open but the sign-in page and the work panel,
 * and reports and contracts are printed without QR codes (they would lead
 * nowhere). Settings → "Public website", or PUBLIC_SITE in .env until it is
 * set there.
 */
final class PublicSite
{
    public static function enabled(): bool
    {
        $value = Setting::main('public_site', '');

        return $value === '' ? (bool) config('fairvalue.public_site') : $value === '1';
    }
}
