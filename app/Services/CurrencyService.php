<?php

namespace App\Services;

use App\Models\CurrencyRate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Official exchange rates of the Central Bank of Uzbekistan (cbu.uz).
 *
 * Rates are stored in the database so the site keeps working when the
 * bank's API is unavailable; the newest stored rates are shown.
 */
class CurrencyService
{
    /**
     * Latest rate per currency, in the order set in config.
     *
     * @return Collection<string, CurrencyRate>
     */
    public function latest(): Collection
    {
        $this->refreshIfStale();

        // Plain arrays are cached (models cannot be unserialised from the cache store).
        $rows = Cache::remember('currency.latest', now()->addMinutes(10), function () {
            $latestDate = CurrencyRate::max('rate_date');

            return $latestDate
                ? CurrencyRate::whereIn('code', config('fairvalue.currency.codes'))->where('rate_date', $latestDate)->get()->map->getAttributes()->all()
                : [];
        });

        $codes = config('fairvalue.currency.codes');

        return CurrencyRate::hydrate($rows)
            ->keyBy('code')
            ->sortBy(fn ($rate) => array_search($rate->code, $codes));
    }

    public function usd(): ?float
    {
        $rate = $this->latest()->get('USD');

        return $rate ? (float) $rate->rate : null;
    }

    /** Converts an amount in UZS to US dollars at the latest rate. */
    public function toUsd(float|string|null $amount): ?float
    {
        $usd = $this->usd();

        return $amount !== null && $usd ? round((float) $amount / $usd, 2) : null;
    }

    /**
     * Downloads today's rates. Returns the number of stored rates.
     */
    public function fetch(): int
    {
        $response = Http::timeout(8)->retry(2, 500, throw: false)->acceptJson()->get(config('fairvalue.currency.source'));
        $response->throw();

        $codes = config('fairvalue.currency.codes');
        $stored = 0;

        foreach ((array) $response->json() as $row) {
            $code = $row['Ccy'] ?? null;
            if (! in_array($code, $codes, true) || ! isset($row['Rate'], $row['Date'])) {
                continue;
            }

            CurrencyRate::updateOrCreate(
                ['code' => $code, 'rate_date' => Carbon::createFromFormat('d.m.Y', $row['Date'])->toDateString()],
                [
                    'nominal' => (int) ($row['Nominal'] ?? 1),
                    'rate' => (float) $row['Rate'],
                    'diff' => (float) ($row['Diff'] ?? 0),
                ]
            );
            $stored++;
        }

        Cache::forget('currency.latest');

        return $stored;
    }

    /**
     * Fetches new rates at most every few hours. The request runs after the
     * response has been sent, so a slow bank API never delays a page; on
     * failure the stored rates keep being shown.
     */
    public function refreshIfStale(): void
    {
        if (app()->runningUnitTests() || app()->runningInConsole() || Cache::has('currency.checked_at')) {
            return;
        }

        // Mark as checked first so that parallel requests do not all call the API.
        Cache::put('currency.checked_at', now(), now()->addMinutes(config('fairvalue.currency.refresh_minutes')));

        dispatch(function () {
            try {
                app(CurrencyService::class)->fetch();
            } catch (Throwable $e) {
                Log::warning('Currency rates could not be fetched: '.$e->getMessage());
            }
        })->afterResponse();
    }
}
