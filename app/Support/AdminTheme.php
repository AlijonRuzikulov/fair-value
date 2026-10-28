<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Look of the admin panel: classic (like the valuation portal the
 * appraisers know, the default) or modern (Settings page).
 */
final class AdminTheme
{
    public static function classic(): bool
    {
        return Setting::get('admin_theme') !== 'modern';
    }
}
