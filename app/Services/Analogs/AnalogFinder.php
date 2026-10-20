<?php

namespace App\Services\Analogs;

use App\Enums\RealEstateFeature;
use App\Models\Analog;
use App\Models\RealEstate;
use App\Services\CurrencyService;
use App\Support\ComparativeCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Collects analogues for the comparative approach: from pasted links, from
 * an AI search of the listing sites, or typed in by hand. Links are saved
 * first and read one at a time (fill), so no request runs for long.
 */
class AnalogFinder
{
    /** Objects that have analogues, by the URL segment of their pages. */
    public const OBJECTS = ['real-estates' => RealEstate::class];

    public const MAX_PER_OBJECT = 30;

    public function __construct(
        private PageFetcher $fetcher,
        private ListingParser $parser,
        private ListingAi $ai,
        private CurrencyService $currency,
        private AnalogPhoto $photos,
    ) {}

    public function aiAvailable(): bool
    {
        return $this->ai->available();
    }

    /**
     * Saves links to be read; returns how many were added.
     *
     * @param  list<string>  $urls
     */
    public function addLinks(Model $object, array $urls): int
    {
        $known = $object->analogs()->pluck('url')->filter()->all();
        $room = max(0, self::MAX_PER_OBJECT - $object->analogs()->count());
        $added = 0;

        foreach (array_unique(array_map('trim', $urls)) as $url) {
            if ($added >= min($room, (int) config('fairvalue.analogs.max_links')) || $url === '' || in_array($url, $known, true) || ! PageFetcher::allowed($url)) {
                continue;
            }
            $object->analogs()->create([
                'status' => Analog::PENDING,
                'url' => $url,
                'source' => parse_url($url, PHP_URL_HOST),
                'created_by' => auth()->id(),
            ]);
            $added++;
        }

        return $added;
    }

    /** Asks the AI for listings similar to the object and saves them to be read. */
    public function search(Model $object): int
    {
        $urls = $this->ai->search(self::subject($object));

        return $this->addLinks($object, array_slice($urls, 0, (int) config('fairvalue.analogs.search_results')));
    }

    /** An analogue typed in by the appraiser. */
    public function addManual(Model $object, array $data): Analog
    {
        $analog = $object->analogs()->make(['created_by' => auth()->id()]);
        $this->apply($object, $analog, [...ListingParser::empty($data['url'] ?? null), ...$data]);
        $analog->save();

        return $analog;
    }

    /**
     * Reads the page of a saved link. A page of search results adds the
     * listings closest to the object as separate analogues.
     *
     * @return Collection<int, Analog> the analogues filled from this page
     */
    public function fill(Analog $analog): Collection
    {
        $object = $analog->object;

        try {
            $page = $this->fetcher->fetch($analog->url);
            $listings = $this->read($page['html'], $page['url']);
        } catch (InvalidArgumentException|RuntimeException $e) {
            $analog->update(['status' => Analog::FAILED, 'note' => mb_substr($e->getMessage(), 0, 250)]);

            return collect([$analog]);
        } catch (Throwable $e) {
            report($e);
            $analog->update(['status' => Analog::FAILED, 'note' => __('The page could not be opened. Check the link or try again later.')]);

            return collect([$analog]);
        }

        $listings = $this->closest($object, $listings);
        $filled = collect();

        foreach ($listings as $i => $listing) {
            if ($i === 0) {
                $target = $analog;
            } else {
                if ($object->analogs()->count() >= self::MAX_PER_OBJECT || ($listing['url'] && $object->analogs()->where('url', $listing['url'])->exists())) {
                    continue;
                }
                $target = $object->analogs()->make(['created_by' => $analog->created_by]);
            }
            // Addresses read from a page are kept only when they lead to a listing site.
            if (! $listing['url'] || ! PageFetcher::allowed($listing['url'])) {
                $listing['url'] = $page['url'];
            }
            $this->apply($object, $target, $listing);
            $target->save();
            $this->photos->download($target);
            $filled->push($target);
        }

        if ($filled->isEmpty()) {
            $analog->update(['status' => Analog::FAILED, 'note' => __('No sale offer was found on the page.')]);

            return collect([$analog]);
        }

        $this->leaveOutOutliers($object, $filled);

        return $filled;
    }

    /**
     * Fills an analogue from the text of its listing, copied from the
     * browser by the appraiser (when the site does not open for the server).
     */
    public function fillFromText(Analog $analog, string $text): Analog
    {
        $object = $analog->object;
        $text = mb_substr(trim(str_replace(["\r\n", "\r"], "\n", $text)), 0, 40000);
        $lines = array_values(array_filter(array_map('trim', explode("\n", $text)), fn ($l) => mb_strlen($l) >= 8));

        $listing = ListingParser::merge(ListingParser::empty($analog->url), ListingParser::fromText($text));
        $listing['title'] ??= $analog->title ?: (isset($lines[0]) ? mb_substr($lines[0], 0, 250) : null);

        if ($this->ai->available()) {
            try {
                $found = $this->ai->extract('URL: '.$analog->url."\n\nPage text (copied from the browser):\n".$text);
                if ($found) {
                    $found[0]['url'] = null;
                    // The AI reads the text as a whole; the labelled values fill what it missed.
                    $listing = ListingParser::merge(array_filter($found[0], fn ($v) => $v !== null), $listing);
                }
            } catch (Throwable $e) {
                report($e);
            }
        }
        $listing['url'] = $analog->url;

        $this->apply($object, $analog, $listing);
        $analog->save();
        $this->leaveOutOutliers($object, collect([$analog]));

        return $analog;
    }

    /** @return list<array<string, mixed>> */
    private function read(string $html, string $url): array
    {
        $own = $this->parser->parse($html, $url);

        if (! $this->ai->available()) {
            return [$own];
        }

        try {
            $found = $this->ai->extract($this->parser->digest($html, $url));
        } catch (Throwable $e) {
            // Without AI the page's own data is still used.
            report($e);

            return [$own];
        }
        if (count($found) === 1) {
            // One listing: values from the page's own data win, AI fills the gaps.
            $found[0]['url'] = null;

            return [ListingParser::merge($own, $found[0])];
        }

        return $found ?: [$own];
    }

    /**
     * For a page with several listings: those closest to the object first.
     *
     * @param  list<array<string, mixed>>  $listings
     * @return list<array<string, mixed>>
     */
    private function closest(Model $object, array $listings): array
    {
        if (count($listings) <= 1) {
            return $listings;
        }

        $subject = self::subject($object);
        $listings = array_values(array_filter($listings, fn ($l) => ($l['price'] ?? 0) > 0 && ($l['area'] ?? 0) > 0));
        usort($listings, fn ($a, $b) => self::distance($subject, $a) <=> self::distance($subject, $b));

        return array_slice($listings, 0, (int) config('fairvalue.analogs.search_results'));
    }

    private static function distance(array $subject, array $listing): float
    {
        $distance = 0.0;
        if (($subject['area'] ?? 0) > 0) {
            $distance += abs(log($listing['area'] / $subject['area']));
        }
        if (($subject['rooms'] ?? null) && ($listing['rooms'] ?? null)) {
            $distance += 0.2 * abs($subject['rooms'] - $listing['rooms']);
        }

        return $distance;
    }

    /** Copies a listing onto an analogue and suggests the adjustments. */
    private function apply(Model $object, Analog $analog, array $listing): void
    {
        $currency = $listing['currency'] ?? 'UZS';
        $price = isset($listing['price']) ? (float) $listing['price'] : null;
        // A dollar price is converted at the rate of the day the offer was made.
        $rate = $currency === 'USD' ? ($this->currency->rateOn('USD', $listing['listed_at'] ?? null) ?? $this->currency->usd()) : null;
        $image = isset($listing['image']) && AnalogPhoto::target((string) $listing['image']) ? mb_substr($listing['image'], 0, 1000) : null;

        $analog->fill([
            'status' => Analog::READY,
            'url' => $listing['url'] ?? $analog->url,
            'source' => isset($listing['url']) ? parse_url($listing['url'], PHP_URL_HOST) : $analog->source,
            'title' => $listing['title'] ?? null,
            'price' => $price,
            'currency' => $currency,
            'usd_rate' => $rate,
            'price_uzs' => $price === null ? null : ($currency === 'USD' ? ($rate ? round($price * $rate, 2) : null) : $price),
            'area' => $listing['area'] ?? null,
            'rooms' => $listing['rooms'] ?? null,
            'floor' => $listing['floor'] ?? null,
            'floors_total' => $listing['floors_total'] ?? null,
            'location' => $listing['location'] ?? null,
            'condition' => $listing['condition'] ?? null,
            'wall_material' => isset($listing['wall_material']) ? mb_substr($listing['wall_material'], 0, 250) : null,
            'communications' => $listing['communications'] ?? null,
            'image_url' => $image ?? $analog->image_url,
            'listed_at' => $listing['listed_at'] ?? null,
            'included' => true,
            'note' => null,
        ]);

        if ($price === null || ! ($analog->area > 0)) {
            $analog->note = __('Price or area was not found — fill them in.');
        } elseif ($currency === 'USD' && ! $rate) {
            $analog->note = __('The dollar rate is not loaded yet.');
        }

        $analog->adjustments = ComparativeCalculator::suggest(self::subject($object), $analog);
    }

    /** @param Collection<int, Analog> $filled */
    private function leaveOutOutliers(Model $object, Collection $filled): void
    {
        $ids = ComparativeCalculator::outliers($object->analogs()->get());
        foreach ($filled as $analog) {
            if (in_array($analog->id, $ids, true)) {
                $analog->update(['included' => false, 'note' => __('The price per m² differs too much from the other offers.')]);
            }
        }
    }

    /** What the analogues are compared with. */
    /** Engineering communications of the object (its features that are communications). */
    public static function communications(Model $object): array
    {
        $keys = array_column(RealEstateFeature::communications(), 'value');

        return array_values(array_intersect($keys, (array) ($object->features ?? [])));
    }

    public static function subject(Model $object): array
    {
        $object->loadMissing(['region', 'district']);

        return [
            'type' => method_exists($object, 'typeLabel') ? $object->typeLabel() : null,
            'title' => $object->name ?? null,
            'area' => $object->total_area !== null ? (float) $object->total_area : null,
            'rooms' => $object->rooms ?? null,
            'floor' => $object->floor ?? null,
            'floors_total' => $object->floors_total ?? null,
            'year_built' => $object->year_built ?? null,
            'condition' => $object->condition ?? null,
            'communications' => self::communications($object),
            'region' => $object->region?->name,
            'district' => $object->district?->name,
            'address' => $object->address,
        ];
    }
}
