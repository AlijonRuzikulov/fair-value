<?php

use App\Services\CurrencyService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('currency:fetch', function (CurrencyService $currency) {
    $count = $currency->fetch();
    $this->info("Stored {$count} exchange rates from the Central Bank of Uzbekistan.");
})->purpose('Download official exchange rates from cbu.uz');

Schedule::command('currency:fetch')->everyThreeHours()->withoutOverlapping();
