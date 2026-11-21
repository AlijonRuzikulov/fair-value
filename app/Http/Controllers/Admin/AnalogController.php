<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RealEstateFeature;
use App\Enums\ValuationApproach;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Analog;
use App\Reports\RealEstateReport;
use App\Services\Analogs\AnalogFinder;
use App\Services\Analogs\AnalogPhoto;
use App\Services\Analogs\PageFetcher;
use App\Services\CurrencyService;
use App\Support\ComparativeCalculator;
use App\Support\NumberInput;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Analogues of the comparative approach on the page of a valuation object.
 */
class AnalogController extends Controller
{
    public function __construct(private AnalogFinder $finder) {}

    /** Adds analogues: pasted links, an AI search, or one typed in by hand. */
    public function store(Request $request, string $type, string $id): RedirectResponse
    {
        $object = $this->object($type, $id);
        $mode = $request->input('mode');

        if ($mode === 'search') {
            if (! $this->finder->aiAvailable()) {
                return $this->back($type, $object)->with('error', __('Automatic search needs an AI key (ANTHROPIC_API_KEY in .env).'));
            }
            set_time_limit(150);
            try {
                $added = $this->finder->search($object);
                Activity::record('analogs', $object, ['mode' => 'search', 'added' => $added]);
            } catch (Throwable $e) {
                report($e);

                return $this->back($type, $object)->with('error', __('The search failed: :reason', ['reason' => mb_substr($e->getMessage(), 0, 200)]));
            }

            return $this->back($type, $object)->with($added ? 'status' : 'error', $added
                ? __(':count listings found, they are being read.', ['count' => $added])
                : __('No suitable listings were found. Paste links to listings yourself.'));
        }

        if ($mode === 'manual') {
            $data = $this->manualData($request);
            $this->finder->addManual($object, $data);
            $this->finder->syncValue($object);
            Activity::record('analogs', $object, ['mode' => 'manual', 'title' => $data['title']]);

            return $this->back($type, $object)->with('status', __('The analogue has been added.'));
        }

        $request->validate(['links' => ['required', 'string', 'max:20000']], [], ['links' => __('Links')]);
        $links = self::links($request->input('links'));
        if ($links === []) {
            return $this->back($type, $object)->with('error', __('No links were found. Copy the address of the listing from the address bar of the browser.'));
        }

        // Listing sites are read by the server; a link to any other site is
        // kept for the appraiser to paste the text of the listing (the server
        // never opens other sites).
        $added = $this->finder->addLinks($object, array_filter($links, PageFetcher::allowed(...)));
        $other = $this->finder->addOtherLinks($object, array_filter($links, fn ($url) => ! PageFetcher::allowed($url)), (int) config('fairvalue.analogs.max_links') - $added);
        Activity::record('analogs', $object, ['mode' => 'links', 'added' => $added + $other]);

        $messages = array_filter([
            $added ? __(':count links added, they are being read.', ['count' => $added]) : null,
            $other ? __(':count links of other sites added: paste the text of each listing under it or fill in the price and the area.', ['count' => $other]) : null,
        ]);

        return $messages
            ? $this->back($type, $object)->with('status', implode(' ', $messages))
            : $this->back($type, $object)->with('error', __('These links have already been added or are not addresses of websites.'));
    }

    /**
     * Web addresses in pasted text: with or without "https://", without the
     * punctuation around them.
     *
     * @return list<string>
     */
    public static function links(string $text): array
    {
        preg_match_all('#(?:https?://|www\.)[^\s<>"\'«»]+|\b(?:[a-z0-9-]+\.)+[a-z]{2,}/[^\s<>"\'«»]*#iu', $text, $m);
        $links = array_map(function (string $url) {
            $url = rtrim($url, '.,;:!?)]}');

            return preg_match('#^https?://#i', $url) ? $url : 'https://'.$url;
        }, $m[0]);

        return array_values(array_unique($links));
    }

    /** Reads the page of one saved link (called by the page, one at a time). */
    public function fill(Analog $analog): JsonResponse
    {
        Gate::authorize('edit-valuation', $analog->object);
        set_time_limit(120);

        if ($analog->isPending()) {
            $this->finder->fill($analog);
            $this->finder->syncValue($analog->object);
        }

        return response()->json(['status' => $analog->fresh()?->status]);
    }

    /**
     * Reads the text of a listing pasted by the appraiser: for sites that do
     * not let the server open their pages.
     */
    public function pasted(Request $request, Analog $analog): RedirectResponse
    {
        Gate::authorize('edit-valuation', $analog->object);
        $data = $request->validate(['text' => ['required', 'string', 'max:60000']], [], ['text' => __('Text of the listing')]);
        set_time_limit(120);

        $this->finder->fillFromText($analog, $data['text']);
        $analog->refresh();
        $this->finder->syncValue($analog->object);

        return $this->back(self::typeOf($analog), $analog->object)
            ->with($analog->isUsable() ? 'status' : 'error', $analog->isUsable() ? __('The listing has been read from the text.') : __('The price or the area was not found in the text — fill them in.'));
    }

    /** Saves corrected values, adjustments and the choice of analogues. */
    public function update(Request $request, string $type, string $id): RedirectResponse
    {
        $object = $this->object($type, $id);
        $rows = (array) $request->input('analogs', []);
        $currency = app(CurrencyService::class);

        foreach ($object->analogs as $analog) {
            $row = $rows[$analog->id] ?? null;
            if (! is_array($row)) {
                continue;
            }

            $price = NumberInput::normalize($row['price'] ?? null);
            $code = in_array($row['currency'] ?? null, ['UZS', 'USD'], true) ? $row['currency'] : $analog->currency;
            $listedAt = isset($row['listed_at']) && strtotime((string) $row['listed_at']) && strtotime((string) $row['listed_at']) <= time()
                ? date('Y-m-d', strtotime((string) $row['listed_at'])) : null;

            // The percentages typed on the grid and the coefficients of the analogue (the rest is worked out).
            $adjustments = array_intersect_key((array) $analog->adjustments, array_flip(['size_coef', 'land_area', 'land_coef', 'completion']));
            // An analogue that was never read starts with the usual percentages (the bargain of an asking price).
            $before = array_intersect_key((array) $analog->adjustments, array_flip(ComparativeCalculator::MANUAL)) ?: ComparativeCalculator::suggest([], $analog);
            foreach (ComparativeCalculator::MANUAL as $key) {
                $value = (float) NumberInput::normalize($row['adjustments'][$key] ?? ($before[$key] ?? 0));
                $adjustments[$key] = max(-90, min(200, round($value, 4)));
            }
            foreach (['size_coef' => [0, 10], 'land_coef' => [0, 10], 'land_area' => [0, 100_000_000], 'completion' => [0, 100]] as $key => [$min, $max]) {
                if (array_key_exists($key, $row)) {
                    $value = NumberInput::normalize($row[$key]);
                    $adjustments[$key] = is_numeric($value) ? max($min, min($max, (float) $value)) : null;
                }
            }

            // Communications: unknown (null) until at least one is marked.
            $communications = isset($row['communications_shown'])
                ? (array_values(array_intersect(array_column(RealEstateFeature::communications(), 'value'), (array) ($row['communications'] ?? []))) ?: null)
                : $analog->communications;

            $analog->fill([
                'communications' => $communications,
                'price' => is_numeric($price) && (float) $price > 0 ? (float) $price : null,
                'currency' => $code,
                'area' => self::positive($row['area'] ?? null),
                'floor' => array_key_exists('floor', $row) ? self::integer($row['floor']) : $analog->floor,
                'floors_total' => array_key_exists('floors_total', $row) ? self::integer($row['floors_total']) : $analog->floors_total,
                'listed_at' => $listedAt,
                'condition' => self::text($row['condition'] ?? null),
                'wall_material' => self::text($row['wall_material'] ?? null),
                'location' => array_key_exists('location', $row) ? self::text($row['location']) : $analog->location,
                'adjustments' => $adjustments,
                'included' => (bool) ($row['included'] ?? false),
            ]);
            // The dollar rate of the day the offer was made: a dollar price is converted at it, the market conditions compare it with today's.
            if ($analog->isDirty(['listed_at', 'currency']) || ! $analog->usd_rate) {
                $analog->usd_rate = $currency->rateOn('USD', $listedAt ?? $analog->created_at) ?? $currency->usd();
            }
            $rate = (float) $analog->usd_rate;
            $analog->price_uzs = $analog->price === null ? null : ($code === 'USD' ? ($rate ? round((float) $analog->price * $rate, 2) : null) : (float) $analog->price);
            // A page that was not read counts once its price and area are typed in.
            if ($analog->status === Analog::FAILED && (float) $analog->price_uzs > 0 && (float) $analog->area > 0) {
                $analog->status = Analog::READY;
            }
            if ($analog->status === Analog::READY && $analog->isUsable() && $analog->isDirty(['price', 'area', 'currency', 'status'])) {
                $analog->note = null;
            }
            $analog->save();
        }

        // What the object brings to the comparison: its coefficients and the price of 1 m² of its land.
        if (is_array($request->input('comparative'))) {
            $typed = [];
            foreach (['size_coef' => 10, 'land_coef' => 10, 'land_price' => 9_999_999_999] as $key => $max) {
                $value = NumberInput::normalize($request->input("comparative.$key"));
                $typed[$key] = is_numeric($value) ? max(0, min($max, (float) $value)) : null;
            }
            $object->report = [...(array) $object->report, 'comparative' => $typed];
        }
        if (in_array($request->input('weighting'), ComparativeCalculator::WEIGHTING, true)) {
            $object->report = [...(array) $object->report, 'weighting' => $request->input('weighting')];
        }
        // Monthly change of prices for the market conditions; empty — not used.
        $badIndex = false;
        if ($request->has('market_index')) {
            $index = NumberInput::normalize($request->input('market_index'));
            $badIndex = $index !== null && $index !== '' && (! is_numeric($index) || abs((float) $index) > 20);
            if (! $badIndex) {
                $object->report = [...(array) $object->report, 'market_index' => $index === null || $index === '' ? null : (float) $index];
            }
        }
        if ($object->isDirty('report')) {
            Activity::muted(fn () => $object->save());
        }
        $this->finder->syncValue($object->fresh());

        return $badIndex
            ? $this->back($type, $object)->with('error', __('The price index must be a number of percent per month between −20 and 20.'))
            : $this->back($type, $object)->with('status', __('Changes saved.'));
    }

    /** Uploads (or replaces) the photo of an analogue shown in the report. */
    public function photo(Request $request, Analog $analog, AnalogPhoto $photos): RedirectResponse
    {
        Gate::authorize('edit-valuation', $analog->object);
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']], [], ['photo' => __('Photo')]);
        $photos->upload($analog, $request->file('photo'));
        Activity::record('uploaded', $analog->object, ['files' => [$request->file('photo')->getClientOriginalName()], 'record' => __('Analogue').' #'.$analog->id]);

        return $this->back(self::typeOf($analog), $analog->object)->with('status', __('The photo has been saved.'));
    }

    public function deletePhoto(Analog $analog, AnalogPhoto $photos): RedirectResponse
    {
        Gate::authorize('edit-valuation', $analog->object);
        $photos->delete($analog);

        return $this->back(self::typeOf($analog), $analog->object)->with('status', __('Deleted.'));
    }

    public function showPhoto(Analog $analog): StreamedResponse
    {
        abort_unless($analog->photo_path && Storage::disk('local')->exists($analog->photo_path), 404);

        return Storage::disk('local')->response($analog->photo_path, 'analog-'.$analog->id.'.'.pathinfo($analog->photo_path, PATHINFO_EXTENSION), ['Cache-Control' => 'private, max-age=86400']);
    }

    /** Writes the result into the comparative approach of the object. */
    public function apply(string $type, string $id): RedirectResponse
    {
        $object = $this->object($type, $id);
        $result = ComparativeCalculator::calculate($object->comparedArea(), $object->analogs, RealEstateReport::comparativeOptions($object, download: true));

        if ($result['value'] === null) {
            return $this->back($type, $object)->with('error', __('Add at least one analogue with a price and an area.'));
        }

        $approaches = collect($object->approaches ?? [])->push(ValuationApproach::Comparative->value)->unique()->values()->all();
        $object->update(['value_comparative' => $result['value'], 'approaches' => $approaches]);

        return $this->back($type, $object)->with('status', __('The value by the comparative approach has been saved.'));
    }

    public function destroy(Analog $analog): RedirectResponse
    {
        $object = $analog->object;
        Gate::authorize('edit-valuation', $object);
        $analog->delete();
        $this->finder->syncValue($object);

        return $this->back(self::typeOf($analog), $object)->with('status', __('Deleted.'));
    }

    private static function typeOf(Analog $analog): string
    {
        return (string) array_search($analog->object::class, AnalogFinder::OBJECTS, true);
    }

    private function object(string $type, string $id): Model
    {
        $class = AnalogFinder::OBJECTS[$type] ?? abort(404);
        $object = $class::findOrFail($id);
        Gate::authorize('edit-valuation', $object);

        return $object;
    }

    private function back(string $type, Model $object): RedirectResponse
    {
        return redirect()->to(route("admin.{$type}.show", $object->id).'#analogs');
    }

    private function manualData(Request $request): array
    {
        $request->merge([
            'price' => NumberInput::normalize($request->input('price')),
            'area' => NumberInput::normalize($request->input('area')),
        ]);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:250'],
            'url' => ['nullable', 'url:http,https', 'max:2000'],
            'price' => ['required', 'numeric', 'min:1', 'max:9999999999999'],
            'currency' => ['required', Rule::in(['UZS', 'USD'])],
            'area' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'rooms' => ['nullable', 'integer', 'min:0', 'max:100'],
            'floor' => ['nullable', 'integer', 'min:-5', 'max:200'],
            'floors_total' => ['nullable', 'integer', 'min:1', 'max:200'],
            'location' => ['nullable', 'string', 'max:250'],
        ], [], [
            'title' => __('Name'), 'price' => __('Price'), 'area' => __('Area, m²'),
        ]);
        $data['price'] = (float) $data['price'];
        $data['area'] = (float) $data['area'];

        return $data;
    }

    private static function positive(mixed $value): ?float
    {
        $value = NumberInput::normalize($value);

        return is_numeric($value) && (float) $value > 0 ? (float) $value : null;
    }

    private static function integer(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private static function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 250);
    }
}
