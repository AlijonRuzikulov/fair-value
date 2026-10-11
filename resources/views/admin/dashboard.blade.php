@php
    use App\Support\Format;
    $active = collect($byModule)->sum('active');

    // Column chart geometry: objects registered per month.
    $w = 720; $h = 240; $left = 34; $right = 8; $top = 18; $bottom = 26;
    $max = max(1, collect($months)->max('total'));
    $step = $max <= 5 ? 1 : ($max <= 10 ? 2 : ($max <= 25 ? 5 : ($max <= 50 ? 10 : (int) ceil($max / 50) * 10)));
    $yMax = (int) ceil($max / $step) * $step;
    $plotH = $h - $top - $bottom;
    $slot = ($w - $left - $right) / count($months);
    $barW = min(24, $slot * 0.56);
    $peak = collect($months)->search(fn ($m) => $m['total'] === $max);
@endphp
<x-layouts.admin :title="__('Dashboard')" nav="dashboard" icon="layout-dashboard" :subtitle="now()->translatedFormat('l, j F Y')">
    <div class="grid grid-4">
        <div class="card stat">
            <span class="stat-icon"><x-icon name="clock" :size="22" /></span>
            <div>
                <p class="stat-label">{{ __('In progress') }}</p>
                <p class="stat-value">{{ Format::number($active) }}</p>
                <p class="stat-note">{{ __('new and in progress objects') }}</p>
            </div>
        </div>
        <div class="card stat">
            <span class="stat-icon green"><x-icon name="badge-check" :size="22" /></span>
            <div>
                <p class="stat-label">{{ __('Completed this month') }}</p>
                <p class="stat-value">{{ Format::number($completedThisMonth) }}</p>
                <p class="stat-note">{{ __('total value') }}: {{ Format::compact($valueThisMonth) }} {{ __('sum') }}</p>
            </div>
        </div>
        <div class="card stat">
            <span class="stat-icon ink"><x-icon name="wallet" :size="22" /></span>
            <div>
                <p class="stat-label">{{ __('Fees this month') }}</p>
                <p class="stat-value">{{ Format::compact($feesThisMonth) }}</p>
                <p class="stat-note">{{ __('for completed valuations, sum') }}</p>
            </div>
        </div>
        <a class="card stat" href="{{ route('admin.leads.index', ['status' => 'new']) }}">
            <span class="stat-icon blue"><x-icon name="inbox" :size="22" /></span>
            <div>
                <p class="stat-label">{{ __('New requests') }}</p>
                <p class="stat-value">{{ Format::number($newLeadsCount) }}</p>
                <p class="stat-note">{{ __('from the website') }}</p>
            </div>
        </a>
    </div>

    <div class="grid grid-main">
        <x-card :title="__('Objects registered in the last 12 months')" icon="chart-column">
            <svg class="chart" viewBox="0 0 {{ $w }} {{ $h }}" role="img" aria-label="{{ __('Objects registered in the last 12 months') }}" data-chart>
                @for ($v = 0; $v <= $yMax; $v += $step)
                    @php $y = $top + $plotH - $v / $yMax * $plotH; @endphp
                    <line class="{{ $v === 0 ? 'axis-line' : 'grid-line' }}" x1="{{ $left }}" x2="{{ $w - $right }}" y1="{{ $y }}" y2="{{ $y }}" />
                    <text x="{{ $left - 8 }}" y="{{ $y + 4 }}" text-anchor="end">{{ $v }}</text>
                @endfor
                @foreach ($months as $i => $month)
                    @php
                        $cx = $left + $slot * $i + $slot / 2;
                        $bh = $month['total'] / $yMax * $plotH;
                        $x = $cx - $barW / 2;
                        $y = $top + $plotH - $bh;
                        $r = min(4, $bh, $barW / 2);
                        $tip = $month['full'].': '.$month['total'];
                        foreach ($modules as $alias => [$label]) {
                            if (! empty($month['split'][$alias])) {
                                $tip .= "\n".$label.': '.$month['split'][$alias];
                            }
                        }
                    @endphp
                    <g data-tip="{{ $tip }}" tabindex="0">
                        <rect x="{{ $left + $slot * $i }}" y="{{ $top }}" width="{{ $slot }}" height="{{ $plotH }}" fill="transparent" />
                        @if ($bh > 0)
                            <path fill="{{ $loop->last ? '#f07c1e' : '#f6b073' }}" d="M{{ $x }},{{ $top + $plotH }} V{{ $y + $r }} Q{{ $x }},{{ $y }} {{ $x + $r }},{{ $y }} H{{ $x + $barW - $r }} Q{{ $x + $barW }},{{ $y }} {{ $x + $barW }},{{ $y + $r }} V{{ $top + $plotH }} Z" />
                        @endif
                        @if (($i === $peak || $loop->last) && $month['total'] > 0)
                            <text x="{{ $cx }}" y="{{ $y - 6 }}" text-anchor="middle" style="fill: var(--ink); font-weight: 700">{{ $month['total'] }}</text>
                        @endif
                        <text x="{{ $cx }}" y="{{ $h - 8 }}" text-anchor="middle">{{ $month['label'] }}</text>
                    </g>
                @endforeach
            </svg>
            <details style="margin-top: 8px">
                <summary class="muted" style="cursor: pointer; font-size: 12px; font-weight: 700">{{ __('Show as a table') }}</summary>
                <div class="table-wrap" style="margin-top: 8px">
                    <table class="table table-sm">
                        <thead><tr><th>{{ __('Month') }}</th>@foreach ($modules as [$label])<th class="t-right">{{ $label }}</th>@endforeach<th class="t-right">{{ __('Total') }}</th></tr></thead>
                        <tbody>
                            @foreach ($months as $month)
                                <tr><td>{{ $month['full'] }}</td>@foreach ($modules as $alias => $m)<td class="t-right num">{{ $month['split'][$alias] ?? 0 }}</td>@endforeach<td class="t-right cell-num">{{ $month['total'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        </x-card>

        <x-card :title="__('By status')" icon="chart-pie">
            @php $statusSum = max(1, $statusTotals->sum()); @endphp
            <div class="status-list">
                @foreach (\App\Enums\ValuationStatus::cases() as $status)
                    <div class="status-line">
                        <span><x-status :status="$status" /></span>
                        <strong class="num">{{ Format::number($statusTotals[$status->value]) }}</strong>
                        <div class="track"><div class="fill fill-{{ $status->tone() }}" style="width: {{ round($statusTotals[$status->value] / $statusSum * 100, 1) }}%"></div></div>
                    </div>
                @endforeach
            </div>
            <p class="field-hint" style="margin-top: 16px">{{ __('All valuation objects: :count', ['count' => Format::number($statusTotals->sum())]) }}</p>
        </x-card>
    </div>

    <div class="grid grid-main">
        <x-card :title="__('Modules')" icon="layers">
            <div class="module-links">
                <a class="module-link" href="{{ route('admin.clients.index') }}">
                    <x-icon name="users" :size="22" />
                    <strong>{{ Format::number($clientsCount) }}</strong>
                    <span>{{ __('Clients') }} @if ($clientsThisMonth)<em class="faint" style="font-style: normal">+{{ $clientsThisMonth }}</em>@endif</span>
                </a>
                @foreach ($modules as $alias => [$label, $icon, $route])
                    <a class="module-link" href="{{ route($route) }}">
                        <x-icon :name="$icon" :size="22" />
                        <strong>{{ Format::number($byModule[$alias]['total']) }}</strong>
                        <span>{{ $label }}@if ($byModule[$alias]['active']) · {{ __(':n open', ['n' => $byModule[$alias]['active']]) }}@endif</span>
                    </a>
                @endforeach
            </div>
        </x-card>

        <x-card :title="__('Central Bank rates')" icon="landmark" flush>
            @if ($rates->isEmpty())
                <x-empty icon="landmark" :title="__('Rates are not loaded yet')" />
            @else
                <ul class="mini-list">
                    @foreach ($rates as $rate)
                        <li>
                            <span class="mini-icon" style="font-weight: 800; font-size: 11px">{{ $rate->code }}</span>
                            <div class="grow"><strong class="num">{{ Format::number($rate->rate, 2) }}</strong> <span class="muted">{{ __('sum') }}</span><small>{{ $rate->rate_date->format('d.m.Y') }}</small></div>
                            @if ($rate->diff != 0)
                                <span @class(['rate-diff', 'up' => $rate->diff > 0, 'down' => $rate->diff < 0])><x-icon :name="$rate->diff > 0 ? 'trending-up' : 'trending-down'" :size="14" />{{ Format::number(abs($rate->diff), 2) }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

    <div class="grid grid-main">
        <x-card :title="__('Recently updated')" icon="history" flush>
            @if ($recent->isEmpty())
                <x-empty :title="__('No objects yet')" />
            @else
                <ul class="mini-list">
                    @foreach ($recent as $object)
                        <li>
                            <span class="mini-icon"><x-icon :name="$modules[$object->getMorphClass()][1]" :size="18" /></span>
                            <div class="grow">
                                <a href="{{ route($object->routePrefix().'.show', $object->id) }}">{{ $object->title }}</a>
                                <small>{{ $object->typeLabel() }} · {{ $object->updated_at->diffForHumans() }}@if ($object->appraiser) · {{ $object->appraiser->name }}@endif</small>
                            </div>
                            <x-status :status="$object->status" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <div class="stack">
            <x-card :title="__('New requests')" icon="inbox" flush>
                <x-slot:actions><a class="btn btn-sm btn-ghost" href="{{ route('admin.leads.index') }}">{{ __('All') }} <x-icon name="arrow-right" :size="14" /></a></x-slot:actions>
                @if ($newLeads->isEmpty())
                    <x-empty icon="inbox" :title="__('No new requests')" />
                @else
                    <ul class="mini-list">
                        @foreach ($newLeads as $lead)
                            <li>
                                <div class="grow">
                                    <a href="{{ route('admin.leads.show', $lead) }}">{{ $lead->name }}</a>
                                    <small>{{ Format::phone($lead->phone) }} · {{ $lead->created_at->diffForHumans() }}</small>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            @if ($workload->isNotEmpty())
                <x-card :title="__('Appraisers workload')" icon="user-cog">
                    @php $maxLoad = max(1, $workload->max('total')); @endphp
                    <div class="status-list">
                        @foreach ($workload as $row)
                            <div class="status-line">
                                <span>{{ $row['user']->name }}</span>
                                <strong class="num">{{ $row['total'] }}</strong>
                                <div class="track"><div class="fill" style="width: {{ round($row['total'] / $maxLoad * 100) }}%; background: #f6b073"></div></div>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.admin>
