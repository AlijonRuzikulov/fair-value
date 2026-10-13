<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CarModel;
use App\Support\CsvExport;
use App\Support\CsvImport;
use App\Support\NumberInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Price lists and norms of the damage module ("Етказилган зарар"):
 * a list with search, a simple form, CSV import / export and a JSON
 * lookup used by the damage calculator.
 */
abstract class CatalogController extends Controller
{
    /** @return class-string<Model> */
    abstract protected function model(): string;

    abstract protected function slug(): string;

    abstract public function nav(): string;

    abstract public function title(): string;

    abstract public function singular(): string;

    abstract public function icon(): string;

    /**
     * Form fields: name => [label, type (text|number|money|date|select|textarea), options?, span?, required?].
     */
    abstract public function fields(): array;

    /** List columns: heading => callback. */
    abstract public function columns(): array;

    abstract protected function rules(): array;

    /** Columns searched by the list search box. */
    abstract protected function searchable(): array;

    /** CSV columns in import / export order: heading => callback for export. */
    abstract protected function csvColumns(): array;

    /** Creates or updates a record from one CSV row (already keyed by position). */
    abstract protected function importRow(array $row): void;

    /** Checks an imported row against the form rules; an invalid row is skipped. */
    protected function checkRow(array $attributes): void
    {
        $validator = Validator::make($attributes, array_intersect_key($this->rules(), $attributes));
        if ($validator->fails()) {
            throw new \InvalidArgumentException($validator->errors()->first());
        }
    }

    /** One entry of the lookup used by the damage calculator. */
    abstract protected function lookupItem(Model $item): array;

    /** Whether records can be tied to a car model. */
    public function hasCarModel(): bool
    {
        return false;
    }

    public function routeName(string $action): string
    {
        return "admin.{$this->slug()}.{$action}";
    }

    /** A short explanation shown above the list. */
    public function description(): ?string
    {
        return null;
    }

    public function csvHeadings(): array
    {
        return array_keys($this->csvColumns());
    }

    public function index(Request $request): View
    {
        $items = $this->query($request)->paginate(25)->withQueryString();

        return view('admin.catalogs.index', [
            'items' => $items,
            'module' => $this,
            'carModels' => $this->hasCarModel() ? CarModel::orderBy('brand')->orderBy('model')->get() : collect(),
        ]);
    }

    public function create(): View
    {
        $class = $this->model();

        return $this->form(new $class);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-catalogs');
        $class = $this->model();
        $class::create($this->validated($request));

        return redirect()->route($this->routeName('index'))->with('status', __('Saved.'));
    }

    public function edit(string $id): View
    {
        return $this->form($this->model()::findOrFail($id));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        Gate::authorize('manage-catalogs');
        $this->model()::findOrFail($id)->update($this->validated($request));

        return redirect()->route($this->routeName('index'))->with('status', __('Changes saved.'));
    }

    public function destroy(string $id): RedirectResponse
    {
        Gate::authorize('manage-catalogs');
        $this->model()::findOrFail($id)->delete();

        return redirect()->route($this->routeName('index'))->with('status', __('Deleted.'));
    }

    public function lookup(Request $request): JsonResponse
    {
        $items = $this->query($request)->limit(20)->get();

        return response()->json($items->map(fn (Model $item) => $this->lookupItem($item)));
    }

    public function export(Request $request): StreamedResponse
    {
        return CsvExport::download($this->slug().'-'.now()->format('Y-m-d').'.csv', $this->query($request), $this->csvColumns());
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:10240', 'mimes:csv,txt']], [], ['file' => __('File')]);

        $rows = CsvImport::rows($request->file('file')->getRealPath());
        $count = 0;
        $failed = 0;

        DB::transaction(function () use ($rows, &$count, &$failed) {
            foreach ($rows as $row) {
                try {
                    $this->importRow($row);
                    $count++;
                } catch (\InvalidArgumentException) {
                    $failed++;
                }
            }
        });

        $message = __(':count rows imported.', ['count' => $count]);
        if ($failed) {
            $message .= ' '.__(':count rows skipped.', ['count' => $failed]);
        }

        return back()->with('status', $message);
    }

    protected function query(Request $request): Builder
    {
        $term = trim((string) $request->query('q'));

        return $this->model()::query()
            ->when($this->hasCarModel(), fn (Builder $q) => $q->with('carModel'))
            ->when($term !== '', function (Builder $q) use ($term) {
                $q->where(function (Builder $w) use ($term) {
                    foreach ($this->searchable() as $column) {
                        $w->orWhere($column, 'like', "%{$term}%");
                    }
                });
            })
            // Records of the chosen model and universal ones (without a model).
            ->when($this->hasCarModel() && $request->filled('car_model_id'), fn (Builder $q) => $q->where(
                fn (Builder $w) => $w->where('car_model_id', $request->integer('car_model_id'))->orWhereNull('car_model_id')
            ))
            ->orderBy($this->searchable()[0]);
    }

    protected function form(Model $item): View
    {
        return view('admin.catalogs.form', [
            'item' => $item,
            'module' => $this,
        ]);
    }

    protected function validated(Request $request): array
    {
        $money = collect($this->fields())->filter(fn ($f) => in_array($f[1], ['money', 'number'], true))->keys()->all();
        $request->merge(collect($request->only($money))->map(fn ($v) => NumberInput::normalize($v))->all());

        $labels = collect($this->fields())->map(fn ($f) => $f[0])->all();

        return $request->validate($this->rules(), [], $labels);
    }

    protected function carModelOptions(): array
    {
        return CarModel::orderBy('brand')->orderBy('model')->get()->pluck('title', 'id')->all();
    }

    /** Finds a car model by "Brand Model" text from a CSV file. */
    protected function carModelId(?string $title): ?int
    {
        $title = trim((string) $title);
        if ($title === '') {
            return null;
        }

        static $models;
        $models ??= CarModel::all()->mapWithKeys(fn ($m) => [mb_strtolower($m->title) => $m->id]);

        return $models[mb_strtolower($title)] ?? throw new \InvalidArgumentException("Unknown car model {$title}");
    }
}
