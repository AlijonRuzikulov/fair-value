@php
    use App\Support\Format;
    use App\Enums\PersonRole;
    // The classic look is the list of the valuation portal: its filters (applied as they change), its columns, no extras.
    $classic = \App\Support\AdminTheme::classic();
    $params = $module->routeParams();
    $currentStatus = $filters['status'] ?? null;
    $total = $statusCounts->sum();
    // Columns in the order of the valuation portal: the first own column (the area), the approaches, the rest.
    $columns = $module->columns();
    $firstColumns = array_slice($columns, 0, 1, true);
    $otherColumns = array_slice($columns, 1, null, true);
    // The filters of the portal's list of this kind of object, and its own columns where it has them.
    $portalFilters = $classic ? $module->portalFilters() : [];
    $portalTable = $classic && view()->exists('admin.'.$module->slugName().'._portal-head');
@endphp
<x-layouts.admin :title="$module->title()" :nav="$nav" :icon="$module->icon()">
    <section class="card list-card">
        <form method="get" class="card-body" action="{{ route($module->routeName('index')) }}" @if ($classic) data-auto-filter @endif>
            @foreach ($params as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
            <div @class(['filters', 'portal-filters' => $classic]) data-filters>
                <div class="field span-3">
                    @if ($classic)
                        <span class="field-label-space" aria-hidden="true"></span>
                    @else
                        <label for="q">{{ __('Search') }}</label>
                    @endif
                    <div class="search-box">
                        <input id="q" class="input" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ $classic ? __('Search') : __('Name, number, client…') }}" aria-label="{{ __('Search') }}">
                        <button type="submit" aria-label="{{ __('Search') }}"><x-icon name="search" :size="16" /></button>
                    </div>
                </div>
                @if ($classic)
                    @if (in_array('extra', $portalFilters, true))@includeIf('admin.'.$module->slugName().'._filters')@endif
                    @if (in_array('status', $portalFilters, true))
                        <x-select name="status" :label="__('Choose the status')" :options="$statuses" :value="$currentStatus" placeholder="" span="3" />
                    @endif
                    @if (in_array('region_id', $portalFilters, true))
                        <x-select name="region_id" :label="$portalTable ? __('Its region') : __('Region')" :options="$regions->pluck('name', 'id')" :value="$filters['region_id'] ?? null" placeholder="" span="3" data-region-select />
                    @endif
                    @if (in_array('district_id', $portalFilters, true))
                        <x-select name="district_id" :label="$portalTable ? __('Its district') : __('District')" :options="$districts" :value="$filters['district_id'] ?? null" placeholder="" span="3" data-district-select />
                    @endif
                    @if (in_array('from', $portalFilters, true))
                        <x-field name="from" type="date" :label="__('Date (from)')" :value="$filters['from'] ?? null" span="3" />
                    @endif
                    @if (in_array('to', $portalFilters, true))
                        <x-field name="to" type="date" :label="__('Date (to)')" :value="$filters['to'] ?? null" span="3" />
                    @endif
                @else
                    @includeIf('admin.'.$module->slugName().'._filters')
                    <x-select name="status" :label="__('Choose the status')" :options="collect($statuses)->map(fn ($label, $value) => $label.' ('.($statusCounts[$value] ?? 0).')')" :value="$currentStatus" placeholder="{{ __('All').' ('.$total.')' }}" span="3" />
                    <x-select name="region_id" :label="__('Region')" :options="$regions->pluck('name', 'id')" :value="$filters['region_id'] ?? null" placeholder="{{ __('All') }}" span="3" data-region-select />
                    <x-select name="district_id" :label="__('District')" :options="$districts" :value="$filters['district_id'] ?? null" placeholder="{{ __('All') }}" span="3" data-district-select />
                    <x-select name="appraiser_id" :label="__('Appraiser')" :options="$appraisers" :value="$filters['appraiser_id'] ?? null" placeholder="{{ __('All') }}" span="3" />
                    <x-field name="from" type="date" :label="__('From')" :value="$filters['from'] ?? null" span="2" />
                    <x-field name="to" type="date" :label="__('To')" :value="$filters['to'] ?? null" span="2" />
                    <div class="filter-actions span-2">
                        <button class="btn btn-dark" type="submit"><x-icon name="filter" :size="16" /> {{ __('Filter') }}</button>
                        <a class="btn btn-ghost btn-icon" href="{{ route($module->routeName('index'), $params) }}" title="{{ __('Reset') }}" aria-label="{{ __('Reset') }}"><x-icon name="x" :size="16" /></a>
                    </div>
                @endif
            </div>
            <button type="button" class="btn btn-sm filter-toggle" data-filter-toggle><x-icon name="sliders-horizontal" :size="14" /> {{ __('Filters') }}</button>
        </form>

        @if ($classic)
            <div class="list-toolbar portal-list-toolbar">
                @if ($module->portalTotal())<p class="list-total">{{ __('Total') }}: {{ $items->total() }}</p>@endif
                <a class="btn btn-success" href="{{ route($module->routeName('create'), $params) }}">{{ __('Add') }}</a>
            </div>
        @else
            <div class="list-toolbar">
                <p class="list-total">{{ __('Total') }}: <strong>{{ $items->total() }}</strong></p>
                <div class="row-flex">
                    <a class="btn" href="{{ route($module->routeName('export'), request()->query()) }}"><x-icon name="file-spreadsheet" :size="16" /> {{ __('Excel') }}</a>
                    <a class="btn btn-primary" href="{{ route($module->routeName('create'), $params) }}"><x-icon name="plus" :size="16" /> {{ __('Add') }}</a>
                </div>
            </div>
        @endif

        @if ($items->isEmpty())
            @php $searching = collect(request()->except(['page', 'kind']))->filter(fn ($value) => filled($value))->isNotEmpty(); @endphp
            <x-empty :icon="$module->icon()" :title="$searching ? null : __('No records yet')" :text="$searching ? __('Try changing the filters or add a new record.') : __('Add the first record — it will appear here.')">
                <a class="btn btn-primary" href="{{ route($module->routeName('create'), $params) }}"><x-icon name="plus" :size="16" /> {{ __('Add') }}</a>
            </x-empty>
        @elseif ($portalTable)
            <div class="table-wrap">
                <table class="table portal-table-list">
                    <thead>@include('admin.'.$module->slugName().'._portal-head')</thead>
                    <tbody>
                        @foreach ($items as $item)
                            @include('admin.'.$module->slugName().'._portal-row')
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $items->links() }}
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('Object') }}</th>
                            @foreach ($firstColumns as $heading => $cb)<th>{{ $heading }}</th>@endforeach
                            @if ($module->usesApproaches())<th>{{ __('Approaches') }}</th>@endif
                            @foreach ($otherColumns as $heading => $cb)<th>{{ $heading }}</th>@endforeach
                            @unless ($module->usesApproaches())<th class="t-right">{{ __('Damage amount') }}</th>@endunless
                            <th class="t-center">{{ __('Attached documents') }}</th>
                            <th>{{ $classic ? __('Valuation status') : __('Status') }}</th>
                            <th>{{ __('Persons') }}</th>
                            @if ($classic)
                                <th>{{ __('Appraiser') }}</th>
                                <th>{{ __('Director of the valuation') }}</th>
                                <th>{{ __('Process') }}</th>
                            @else
                                <th class="t-actions"><span class="sr-only">{{ __('Actions') }}</span></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            @if ($classic && $module->slugName() === 'real-estates')
                                @include('admin.real-estates._portal-row')
                                @continue
                            @endif
                            <tr>
                                <td class="muted num">{{ $item->id }}</td>
                                <td style="min-width: 200px; max-width: 280px">
                                    <a class="cell-title" href="{{ route($module->routeName('show'), $item->id) }}">{{ $item->title }}</a>
                                    <span class="cell-sub">{{ $item->locationLine() ?: '—' }}</span>
                                    @if ($item->report_number)<span class="cell-sub">{{ __('Report') }} № {{ $item->report_number }}</span>@endif
                                    @if ($module->usesApproaches() && $item->market_value !== null)<span class="cell-sub cell-num">{{ Format::money($item->market_value) }}</span>@endif
                                </td>
                                @foreach ($firstColumns as $cb)<td>{{ $cb($item) }}</td>@endforeach
                                @if ($module->usesApproaches())
                                    <td>
                                        <div class="chips">
                                            @forelse ($item->approachList() as $approach)
                                                <span class="chip">{{ $approach->short() }}</span>
                                            @empty
                                                <span class="muted">—</span>
                                            @endforelse
                                        </div>
                                    </td>
                                @endif
                                @foreach ($otherColumns as $cb)<td>{{ $cb($item) }}</td>@endforeach
                                @unless ($module->usesApproaches())<td class="t-right cell-num">{{ $item->market_value !== null ? Format::compact($item->market_value) : '—' }}</td>@endunless
                                <td class="t-center"><span @class(['files-count', 'has' => $item->attachments_count])><x-icon name="paperclip" :size="15" />{{ $item->attachments_count }}</span></td>
                                <td><x-status :status="$item->status" /></td>
                                <td style="min-width: 130px">
                                    <div class="persons">
                                        @forelse ($item->clients->take(2) as $person)
                                            <span class="person">{{ $person->short_name }}<small>{{ PersonRole::labelFor($person->pivot->role) }}</small></span>
                                        @empty
                                            <span class="muted">—</span>
                                        @endforelse
                                        @if ($item->clients->count() > 2)<span class="faint">+{{ $item->clients->count() - 2 }}</span>@endif
                                    </div>
                                </td>
                                @if ($classic)
                                    <td>{{ $item->appraiser?->name ?? '—' }}</td>
                                    <td>{{ $item->currentStep('approved')?->user?->name ?? '—' }}</td>
                                    <td class="row-buttons">
                                        <a class="btn btn-sm btn-info btn-icon" href="{{ route($module->routeName('show'), $item->id) }}" title="{{ __('Photos and documents') }}" aria-label="{{ __('Photos and documents') }}"><x-icon name="image" :size="15" /></a>
                                        @can('edit-valuation', $item)<a class="btn btn-sm btn-warning btn-icon" href="{{ route($module->routeName('edit'), $item->id) }}" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}"><x-icon name="square-pen" :size="15" /></a>@endcan
                                    </td>
                                @else
                                <td class="t-actions">
                                    <div class="row-actions">
                                        <a class="btn btn-sm btn-icon" href="{{ route($module->routeName('show'), $item->id) }}" title="{{ __('View') }}" aria-label="{{ __('View') }}"><x-icon name="eye" :size="16" /></a>
                                        @can('edit-valuation', $item)<a class="btn btn-sm btn-icon" href="{{ route($module->routeName('edit'), $item->id) }}" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}"><x-icon name="pencil" :size="16" /></a>@endcan
                                        <a class="btn btn-sm btn-icon" href="{{ route($module->routeName('print'), $item->id) }}" target="_blank" title="{{ __('Print') }}" aria-label="{{ __('Print') }}"><x-icon name="printer" :size="16" /></a>
                                    </div>
                                </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $items->links() }}
        @endif
    </section>
</x-layouts.admin>
