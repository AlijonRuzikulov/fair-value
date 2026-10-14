<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Interface languages
    |--------------------------------------------------------------------------
    |
    | "oz" is Uzbek in Cyrillic script, "uz" is Uzbek in Latin script. The
    | first entry is used when nothing else is selected (see APP_LOCALE).
    | "carbon" is the locale used for month and weekday names.
    |
    */

    'locales' => [
        'oz' => ['label' => 'Ўзбекча', 'short' => 'ЎЗ', 'carbon' => 'uz_Cyrl', 'html' => 'uz-Cyrl'],
        'uz' => ['label' => "O'zbekcha", 'short' => 'UZ', 'carbon' => 'uz_Latn', 'html' => 'uz-Latn'],
        'ru' => ['label' => 'Русский', 'short' => 'РУ', 'carbon' => 'ru', 'html' => 'ru'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Company defaults
    |--------------------------------------------------------------------------
    |
    | Used until the values are changed in the admin panel (Settings page).
    |
    */

    'company' => [
        'name' => 'Fair Value',
        'legal_name' => '"FAIR VALUE" MChJ',
        'phone' => '+998 90 159-01-02',
        'phone2' => '',
        'email' => 'info@fairvalue.uz',
        'address' => '',
        'working_hours' => '09:00 – 18:00',
        'telegram' => '',
        'instagram' => 'fairvalueuz',
        'license' => '',
        'inn' => '',
        'bank_account' => '',
        'bank_name' => '',
        'bank_mfo' => '',
        'director' => '',
        'map_url' => '',
        // Numbers on the home page; empty ones are not shown.
        'stat_years' => '',
        'stat_reports' => '',
        'stat_clients' => '',
        'stat_banks' => '',
        'labor_rate' => '120000',
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency rates
    |--------------------------------------------------------------------------
    |
    | Official rates are taken from the Central Bank of Uzbekistan.
    |
    */

    'currency' => [
        'source' => env('CBU_RATES_URL', 'https://cbu.uz/uz/arkhiv-kursov-valyut/json/'),
        'codes' => ['USD', 'EUR', 'RUB', 'GBP', 'CNY', 'KZT'],
        'refresh_minutes' => 180,
    ],

    /*
    |--------------------------------------------------------------------------
    | First administrator
    |--------------------------------------------------------------------------
    |
    | Created by "php artisan db:seed". When no password is set a random one
    | is generated and printed to the console.
    |
    */

    'admin' => [
        'email' => env('ADMIN_EMAIL', 'admin@fairvalue.uz'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo mode
    |--------------------------------------------------------------------------
    |
    | Shows the demo account on the sign-in page. Turned on by
    | "composer demo" and in Codespaces; never enable it on the real site.
    |
    */

    'demo' => (bool) env('DEMO_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Analogues for the comparative approach
    |--------------------------------------------------------------------------
    |
    | Sale offers are read only from these sites (and their subdomains).
    | Adjustments are percentages suggested for every analogue; the appraiser
    | can change each of them.
    |
    */

    'analogs' => [
        'domains' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'ANALOG_DOMAINS', 'olx.uz,uybor.uz,realt24.uz,domtut.uz'
        ))))),
        'max_links' => 10,
        'search_results' => 8,
        'bargain' => -5.0,           // asking price → sale price
        'first_floor' => -5.0,       // compared with a middle floor
        'last_floor' => -3.0,
        'area_elasticity' => 0.1,    // larger flats cost less per m²
        'max_area_adjustment' => 10.0,
        'outlier' => 0.35,           // further than ±35 % from the median → left out
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit log
    |--------------------------------------------------------------------------
    |
    | Records older than this are removed every night (php artisan audit:prune).
    |
    */

    'audit' => [
        'retention_days' => (int) env('AUDIT_RETENTION_DAYS', 1095),
    ],

    'uploads' => [
        'max_kb' => 20480,
        'mimes' => 'pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp,heic,zip,rar,txt',
    ],

];
