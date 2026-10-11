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
        'stat_years' => '10+',
        'stat_reports' => '5000+',
        'stat_clients' => '3000+',
        'stat_banks' => '20+',
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

    'uploads' => [
        'max_kb' => 20480,
        'mimes' => 'pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp,heic,zip,rar,txt',
    ],

];
