<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ValuationApproach;
use App\Http\Controllers\Controller;
use App\Models\RealEstate;
use App\Support\CostApproach;
use App\Support\IncomeApproach;
use App\Support\NumberInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The cost and income approaches of real estate, worked out on the object
 * page: the data is kept with the report, the value goes to the approach.
 */
class ApproachController extends Controller
{
    public function cost(Request $request, string $id): RedirectResponse
    {
        $item = $this->object($id);
        $rows = collect((array) $request->input('buildings', []))
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row) => [
                'name' => mb_substr(trim((string) ($row['name'] ?? '')), 0, 120),
                'unit' => ($row['unit'] ?? 'm2') === 'm3' ? 'm3' : 'm2',
                'quantity' => NumberInput::normalize($row['quantity'] ?? null),
                'rate' => NumberInput::normalize($row['rate'] ?? null),
                'year_built' => NumberInput::normalize($row['year_built'] ?? null),
                'life' => NumberInput::normalize($row['life'] ?? null),
                'wear' => NumberInput::normalize($row['wear'] ?? null),
                'functional' => NumberInput::normalize($row['functional'] ?? null),
                'external' => NumberInput::normalize($row['external'] ?? null),
                'light' => (bool) ($row['light'] ?? false),
            ])
            ->filter(fn ($row) => $row['quantity'] !== null || $row['rate'] !== null || $row['name'] !== '')
            ->values()->all();

        $request->merge(['buildings' => $rows, 'profit' => NumberInput::normalize($request->input('profit')), 'land' => NumberInput::normalize($request->input('land'))]);
        $data = $request->validate([
            'buildings' => ['array', 'max:20'],
            'buildings.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'buildings.*.rate' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'buildings.*.year_built' => ['nullable', 'integer', 'min:1800', 'max:'.now()->year],
            'buildings.*.life' => ['nullable', 'integer', 'min:5', 'max:200'],
            'buildings.*.wear' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'buildings.*.functional' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'buildings.*.external' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'profit' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'land' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999'],
        ], [], [
            'buildings.*.quantity' => __('Area or volume'), 'buildings.*.rate' => __('Replacement cost per unit'),
            'buildings.*.year_built' => __('Year built'), 'land' => __('Land value (right of use)'),
        ]);

        $input = ['buildings' => array_values($rows), 'profit' => (float) ($data['profit'] ?? 0), 'land' => (float) ($data['land'] ?? 0)];
        $result = CostApproach::calculate($input, (int) ($item->valuation_date ?? now())->format('Y'));
        $this->save($item, 'cost', $input, ValuationApproach::Cost, $result['value']);

        return redirect()->to(route('admin.real-estates.show', $item->id).'#cost-approach')->with('status', __('Changes saved.'));
    }

    public function income(Request $request, string $id): RedirectResponse
    {
        $item = $this->object($id);
        $fields = ['area', 'rent', 'vacancy', 'opex', 'cap'];
        $request->merge(collect($request->only($fields))->map(fn ($v) => NumberInput::normalize($v))->all());
        $data = $request->validate([
            'area' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'rent' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'vacancy' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'opex' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cap' => ['required', 'numeric', 'gt:0', 'max:100'],
        ], [], ['area' => __('Rentable area'), 'rent' => __('Monthly rent per m²'), 'cap' => __('Capitalisation rate, %')]);

        $input = array_map(fn ($v) => (float) $v, array_merge(array_fill_keys($fields, 0), $data));
        $result = IncomeApproach::calculate($input);
        $this->save($item, 'income', $input, ValuationApproach::Income, $result['value']);

        return redirect()->to(route('admin.real-estates.show', $item->id).'#income-approach')->with('status', __('Changes saved.'));
    }

    /** Keeps the data with the report and, when the approach is applied, its value. */
    private function save(RealEstate $item, string $key, array $input, ValuationApproach $approach, ?float $value): void
    {
        $item->report = [...(array) $item->report, $key => $input];
        if (in_array($approach, $item->approachList(), true)) {
            $item->{$approach->column()} = $value;
        }
        $item->save();
    }

    private function object(string $id): RealEstate
    {
        $item = RealEstate::findOrFail($id);
        Gate::authorize('edit-valuation', $item);

        return $item;
    }
}
