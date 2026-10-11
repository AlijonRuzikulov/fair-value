<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeadStatus;
use App\Enums\ValuationStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Lead;
use App\Models\User;
use App\Services\CurrencyService;
use App\Support\Registry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Morph alias => [label, icon, route]. */
    private function modules(): array
    {
        return [
            'real_estate' => [__('Real estate'), 'building', 'admin.real-estates.index'],
            'asset' => [__('Movable property'), 'boxes', 'admin.assets.index'],
            'vehicle' => [__('Vehicles'), 'car', 'admin.vehicles.index'],
            'damage_case' => [__('Damage assessments'), 'wrench', 'admin.damage-cases.index'],
            'other_asset' => [__('Other property'), 'monitor', 'admin.other-assets.index'],
        ];
    }

    public function __invoke(CurrencyService $currency): View
    {
        $monthStart = now()->startOfMonth();
        $chartStart = now()->subMonths(11)->startOfMonth();

        $byModule = [];
        $statuses = collect();
        $created = collect();
        $recent = collect();
        $completedThisMonth = 0;
        $feesThisMonth = 0.0;
        $valueThisMonth = 0.0;

        foreach (Registry::valuationModels() as $alias => $class) {
            $byModule[$alias] = [
                'total' => $class::count(),
                'active' => $class::whereIn('status', [ValuationStatus::New->value, ValuationStatus::InProgress->value])->count(),
            ];

            $statuses = $statuses->merge(
                $class::query()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->get()
            );

            // Grouped by month in PHP to stay independent of the database engine.
            $created = $created->merge(
                $class::where('created_at', '>=', $chartStart)->pluck('created_at')->map(fn ($d) => ['alias' => $alias, 'date' => Carbon::parse($d)])
            );

            $completed = $class::where('completed_at', '>=', $monthStart);
            $completedThisMonth += (clone $completed)->count();
            $feesThisMonth += (float) (clone $completed)->sum('service_fee');
            $valueThisMonth += (float) (clone $completed)->sum('market_value');

            $recent = $recent->merge($class::with('appraiser')->latest('updated_at')->limit(6)->get());
        }

        $statusTotals = collect(ValuationStatus::cases())->mapWithKeys(fn ($s) => [
            $s->value => (int) $statuses->where('status', $s->value)->sum('total'),
        ]);

        return view('admin.dashboard', [
            'modules' => $this->modules(),
            'byModule' => $byModule,
            'statusTotals' => $statusTotals,
            'months' => $this->months($created, $chartStart),
            'recent' => $recent->sortByDesc('updated_at')->take(8)->values(),
            'completedThisMonth' => $completedThisMonth,
            'feesThisMonth' => $feesThisMonth,
            'valueThisMonth' => $valueThisMonth,
            'clientsCount' => Client::count(),
            'clientsThisMonth' => Client::where('created_at', '>=', $monthStart)->count(),
            'newLeads' => Lead::where('status', LeadStatus::New)->latest('id')->limit(5)->get(),
            'newLeadsCount' => Lead::where('status', LeadStatus::New)->count(),
            'workload' => $this->workload(),
            'rates' => $currency->latest(),
        ]);
    }

    /**
     * Objects registered per month for the last 12 months, with the split by type.
     *
     * @return list<array{label:string, total:int, split:array<string,int>}>
     */
    private function months(Collection $created, Carbon $start): array
    {
        $months = [];
        for ($i = 0; $i < 12; $i++) {
            $month = $start->copy()->addMonths($i);
            $inMonth = $created->filter(fn ($row) => $row['date']->isSameMonth($month));
            $months[] = [
                'label' => $month->translatedFormat('M'),
                'full' => $month->translatedFormat('F Y'),
                'total' => $inMonth->count(),
                'split' => $inMonth->countBy('alias')->all(),
            ];
        }

        return $months;
    }

    /** Open objects per appraiser. */
    private function workload(): Collection
    {
        $counts = collect();
        foreach (Registry::valuationModels() as $class) {
            $class::whereIn('status', [ValuationStatus::New->value, ValuationStatus::InProgress->value])
                ->whereNotNull('appraiser_id')
                ->toBase()
                ->selectRaw('appraiser_id, count(*) as total')
                ->groupBy('appraiser_id')
                ->get()
                ->each(fn ($row) => $counts[$row->appraiser_id] = ($counts[$row->appraiser_id] ?? 0) + $row->total);
        }

        $users = User::whereIn('id', $counts->keys())->get()->keyBy('id');

        return $counts->sortDesc()->map(fn ($total, $id) => ['user' => $users[$id] ?? null, 'total' => $total])->filter(fn ($r) => $r['user'])->values();
    }
}
