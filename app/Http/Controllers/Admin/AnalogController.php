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
            Activity::record('analogs', $object, ['mode' => 'manual', 'title' => $data['title']]);

            return $this->back($type, $object)->with('status', __('The analogue has been added.'));
        }

        $request->validate(['links' => ['required', 'string', 'max:20000']], [], ['links' => __('Links')]);
        preg_match_all('#https?://[^\s<>"\']+#u', $request->input('links'), $m);
        $rejected = array_filter($m[0], fn ($url) => ! PageFetcher::allowed($url));
        $added = $this->finder->addLinks($object, $m[0]);
        Activity::record('analogs', $object, ['mode' => 'links', 'added' => $added]);

        $response = $this->back($type, $object);
        if ($rejected) {
            $response->with('error', __('Only links to these sites are accepted: :sites.', ['sites' => implode(', ', config('fairvalue.analogs.domains'))]));
        }

        return $response->with('status', __(':count links added, they are being read.', ['count' => $added]));
    }

    /** Reads the page of one saved link (called by the page, one at a time). */
    public function fill(Analog $analog): JsonResponse
    {
        Gate::authorize('edit-valuation', $analog->object);
        set_time_limit(120);

        if ($analog->isPending()) {
            $this->finder->fill($analog);
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

            $adjustments = [];
            foreach (ComparativeCalculator::ADJUSTMENTS as $key) {
                $value = (float) NumberInput::normalize($row['adjustments'][$key] ?? 0);
                $adjustments[$key] = max(-50, min(50, round($value, 2)));
            }

            // Communications: unknown (null) until at least one is marked.
            $communications = isset($row['communications_shown'])
                ? (array_values(array_intersect(array_column(RealEstateFeature::communications(), 'value'), (array) ($row['communications'] ?? []))) ?: null)
                : $analog->communications;
            if ($communications !== $analog->communications) {
                $adjustments['amenities'] = ComparativeCalculator::amenities(AnalogFinder::communications($object), $communications);
            }

            $analog->fill([
                'communications' => $communications,
                'price' => is_numeric($price) && (float) $price > 0 ? (float) $price : null,
                'currency' => $code,
                'area' => self::positive($row['area'] ?? null),
                'floor' => self::integer($row['floor'] ?? null),
                'floors_total' => self::integer($row['floors_total'] ?? null),
                'listed_at' => $listedAt,
                'condition' => self::text($row['condition'] ?? null),
                'wall_material' => self::text($row['wall_material'] ?? null),
                'adjustments' => $adjustments,
                'included' => (bool) ($row['included'] ?? false),
            ]);
            // A dollar price is converted at the rate of the day the offer was made.
            if ($code !== 'USD') {
                $analog->usd_rate = null;
            } elseif ($analog->isDirty(['listed_at', 'currency']) || ! $analog->usd_rate) {
                $analog->usd_rate = $currency->rateOn('USD', $listedAt ?? $analog->created_at) ?? $currency->usd();
            }
            $rate = (float) $analog->usd_rate;
            $analog->price_uzs = $analog->price === null ? null : ($code === 'USD' ? ($rate ? round((float) $analog->price * $rate, 2) : null) : (float) $analog->price);
            if ($analog->status === Analog::READY && $analog->isUsable() && $analog->isDirty(['price', 'area', 'currency'])) {
                $analog->note = null;
            }
            $analog->save();
        }

        if (in_array($request->input('weighting'), ComparativeCalculator::WEIGHTING, true)) {
            $object->report = [...(array) $object->report, 'weighting' => $request->input('weighting')];
            Activity::muted(fn () => $object->save());
        }

        return $this->back($type, $object)->with('status', __('Changes saved.'));
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
        $result = ComparativeCalculator::calculate((float) $object->total_area, $object->analogs, RealEstateReport::comparativeOptions($object, download: true));

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
