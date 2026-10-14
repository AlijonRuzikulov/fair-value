<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ValuationApproach;
use App\Http\Controllers\Controller;
use App\Models\Analog;
use App\Services\Analogs\AnalogFinder;
use App\Services\Analogs\PageFetcher;
use App\Services\CurrencyService;
use App\Support\ComparativeCalculator;
use App\Support\NumberInput;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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

            return $this->back($type, $object)->with('status', __('The analogue has been added.'));
        }

        $request->validate(['links' => ['required', 'string', 'max:20000']], [], ['links' => __('Links')]);
        preg_match_all('#https?://[^\s<>"\']+#u', $request->input('links'), $m);
        $rejected = array_filter($m[0], fn ($url) => ! PageFetcher::allowed($url));
        $added = $this->finder->addLinks($object, $m[0]);

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

    /** Saves corrected values, adjustments and the choice of analogues. */
    public function update(Request $request, string $type, string $id): RedirectResponse
    {
        $object = $this->object($type, $id);
        $rows = (array) $request->input('analogs', []);

        foreach ($object->analogs as $analog) {
            $row = $rows[$analog->id] ?? null;
            if (! is_array($row)) {
                continue;
            }

            $price = NumberInput::normalize($row['price'] ?? null);
            $currency = in_array($row['currency'] ?? null, ['UZS', 'USD'], true) ? $row['currency'] : $analog->currency;
            $rate = $currency === 'USD' ? ((float) $analog->usd_rate ?: app(CurrencyService::class)->usd()) : null;

            $adjustments = [];
            foreach (ComparativeCalculator::ADJUSTMENTS as $key) {
                $value = (float) NumberInput::normalize($row['adjustments'][$key] ?? 0);
                $adjustments[$key] = max(-50, min(50, round($value, 1)));
            }

            $analog->fill([
                'price' => is_numeric($price) && (float) $price > 0 ? (float) $price : null,
                'currency' => $currency,
                'usd_rate' => $rate,
                'area' => self::positive($row['area'] ?? null),
                'floor' => self::integer($row['floor'] ?? null),
                'floors_total' => self::integer($row['floors_total'] ?? null),
                'adjustments' => $adjustments,
                'included' => (bool) ($row['included'] ?? false),
            ]);
            $analog->price_uzs = $analog->price === null ? null : ($currency === 'USD' ? ($rate ? round((float) $analog->price * $rate, 2) : null) : (float) $analog->price);
            if ($analog->status === Analog::READY && $analog->isUsable() && $analog->isDirty(['price', 'area', 'currency'])) {
                $analog->note = null;
            }
            $analog->save();
        }

        return $this->back($type, $object)->with('status', __('Changes saved.'));
    }

    /** Writes the result into the comparative approach of the object. */
    public function apply(string $type, string $id): RedirectResponse
    {
        $object = $this->object($type, $id);
        $result = ComparativeCalculator::calculate((float) $object->total_area, $object->analogs);

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

        $type = array_search($object::class, AnalogFinder::OBJECTS, true);

        return $this->back($type, $object)->with('status', __('Deleted.'));
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
}
