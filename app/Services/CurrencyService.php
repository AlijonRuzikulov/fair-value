<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\CurrencyRate;
use DateTimeInterface;
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
     * Official rate of a currency (sum per unit) on a date — the valuation
     * date or the date of a sale offer. A missing day is downloaded from the
     * bank's archive once (unless $download is false, e.g. while a page is
     * shown); otherwise the closest earlier stored rate is used.
     */
    public function rateOn(string $code, DateTimeInterface|string|null $date, bool $download = true): ?float
    {
        $date = Carbon::parse($date ?? now())->startOfDay();
        if ($date->isFuture()) {
            $date = now()->startOfDay();
        }

        $find = fn () => CurrencyRate::where('code', $code)
            ->whereDate('rate_date', '<=', $date->toDateString())
            ->whereDate('rate_date', '>=', $date->copy()->subDays(10)->toDateString())
            ->orderByDesc('rate_date')->first();

        $rate = $find();
        if ($download && (! $rate || ! $rate->rate_date->isSameDay($date)) && config('fairvalue.currency.history')
            && Cache::add('currency.archive.'.$date->toDateString(), true, now()->addHours(6))) {
            try {
                $this->fetch($date);
                $rate = $find();
            } catch (Throwable $e) {
                Log::warning('Archive exchange rates could not be fetched: '.$e->getMessage());
            }
        }

        // No rate near that day: the closest one at all is better than none.
        $rate ??= CurrencyRate::where('code', $code)->whereDate('rate_date', '<=', $date->toDateString())->orderByDesc('rate_date')->first()
            ?? CurrencyRate::where('code', $code)->orderBy('rate_date')->first();

        return $rate ? round((float) $rate->rate / max(1, (int) $rate->nominal), 4) : null;
    }

    /**
     * Downloads the rates of today (or of a past date from the archive).
     * Returns the number of stored rates.
     */
    public function fetch(?DateTimeInterface $date = null): int
    {
        $url = config('fairvalue.currency.source');
        if ($date) {
            $url = rtrim($url, '/').'/all/'.$date->format('Y-m-d').'/';
        }
        $response = Http::timeout(8)->retry(2, 500, throw: false)->acceptJson()->get($url);
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
                if (Cache::add('audit:currency-failed', true, now()->addHours(6))) {
                    Activity::record('system', null, ['task' => 'currency', 'error' => mb_substr($e->getMessage(), 0, 300)], 'Exchange rates were not downloaded');
                }
            }
        })->afterResponse();
    }
}
