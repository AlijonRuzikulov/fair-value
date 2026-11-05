<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Look of the admin panel: classic (like the valuation portal the
 * appraisers know, the default) or modern, and the colour of the classic
 * menu (Settings page).
 */
final class AdminTheme
{
    public static function classic(): bool
    {
        return Setting::get('admin_theme') !== 'modern';
    }

    /** Colour of the classic menu: light blue (the default) or orange, as on the portal. */
    public static function orange(): bool
    {
        return Setting::get('admin_color') === 'orange';
    }
}
