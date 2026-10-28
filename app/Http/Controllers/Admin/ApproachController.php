<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ValuationApproach;
use App\Http\Controllers\Controller;
use App\Models\RealEstate;
use App\Reports\RealEstateReport;
use App\Support\ComparativeCalculator;
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

    /**
     * An approach applied, or refused with the reason printed in the report
     * ("voz kechildi" on the object page of the valuation portal).
     */
    public function toggle(Request $request, string $id, string $approach): RedirectResponse
    {
        $item = $this->object($id);
        $case = ValuationApproach::from($approach);
        $data = $request->validate([
            'applied' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ], [], ['reason' => __('Reason')]);

        $report = (array) $item->report;
        $applied = array_map(fn (ValuationApproach $a) => $a->value, $item->approachList());
        if ($data['applied']) {
            $applied[] = $case->value;
            // The value worked out again from what was typed before the refusal.
            $item->{$case->column()} = match ($case) {
                ValuationApproach::Cost => isset($report['cost']) ? CostApproach::calculate($report['cost'], (int) ($item->valuation_date ?? now())->format('Y'))['value'] : null,
                ValuationApproach::Income => isset($report['income']) ? IncomeApproach::calculate($report['income'])['value'] : null,
                ValuationApproach::Comparative => ComparativeCalculator::calculate((float) $item->total_area, $item->analogs, RealEstateReport::comparativeOptions($item))['value'],
            };
        } else {
            $applied = array_diff($applied, [$case->value]);
            $item->{$case->column()} = null;
        }
        // The reason typed with a refusal; otherwise the standard text of the report.
        if (! $data['applied'] && filled($data['reason'] ?? null)) {
            $report['reason_'.$case->value] = $data['reason'];
        } else {
            unset($report['reason_'.$case->value]);
        }

        $item->approaches = array_values(array_filter(
            array_map(fn (ValuationApproach $a) => $a->value, ValuationApproach::cases()),
            fn ($value) => in_array($value, $applied, true)
        ));
        $item->report = $report;
        $item->save();

        return redirect()->to(route('admin.real-estates.show', $item->id).'#approach-'.$case->value)
            ->with('status', $data['applied'] ? __('The approach is applied.') : __('The approach is refused.'));
    }

    /** The final value of the reconciliation: typed, or the reconciled one rounded to thousands. */
    public function value(Request $request, string $id): RedirectResponse
    {
        $item = $this->object($id);
        if ($request->boolean('reconciled')) {
            $reconciled = RealEstateReport::reconciliation($item)['reconciled'];
            if ($reconciled <= 0) {
                return back()->with('error', __('Enter the values of the applied approaches first.'));
            }
            $item->update(['market_value' => round($reconciled, -3)]);
        } else {
            $request->merge(['market_value' => NumberInput::normalize($request->input('market_value'))]);
            $data = $request->validate(['market_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999']], [], ['market_value' => __('Market value')]);
            $item->update(['market_value' => $data['market_value']]);
        }

        return redirect()->to(route('admin.real-estates.show', $item->id).'#reconciliation')->with('status', __('Changes saved.'));
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
