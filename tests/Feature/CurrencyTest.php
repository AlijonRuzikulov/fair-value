<?php

namespace Tests\Feature;

use App\Models\CurrencyRate;
use App\Services\CurrencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_rates_are_downloaded_from_the_central_bank(): void
    {
        Http::fake(['cbu.uz/*' => Http::response([
            ['Ccy' => 'USD', 'Nominal' => '1', 'Rate' => '11814.05', 'Diff' => '-12.3', 'Date' => '10.10.2026'],
            ['Ccy' => 'EUR', 'Nominal' => '1', 'Rate' => '13482.19', 'Diff' => '5.1', 'Date' => '10.10.2026'],
            ['Ccy' => 'JPY', 'Nominal' => '1', 'Rate' => '80.1', 'Diff' => '0', 'Date' => '10.10.2026'],
        ])]);

        $service = app(CurrencyService::class);
        $this->artisan('currency:fetch')->assertSuccessful();

        $this->assertSame(2, CurrencyRate::count());
        $this->assertSame(['USD', 'EUR'], $service->latest()->keys()->all());
        $this->assertSame(11814.05, $service->usd());
        $this->assertSame(1000.0, $service->toUsd(11814050));
    }

    public function test_pages_work_without_any_rates(): void
    {
        $this->get('/')->assertOk();
        $this->assertNull(app(CurrencyService::class)->usd());
    }
}
