@php
    use App\Support\Format;
    use App\Enums\PersonRole;
    $params = $module->routeParams();
    $currentStatus = $filters['status'] ?? null;
    $total = $statusCounts->sum();
@endphp
<x-layouts.admin :title="$module->title()" :nav="$nav" :icon="$module->icon()">
    <section class="card">
        <form method="get" class="card-body" action="{{ route($module->routeName('index')) }}">
            @foreach ($params as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
            @if ($currentStatus)<input type="hidden" name="status" value="{{ $currentStatus }}">@endif
            <div class="filters" data-filters>
                <div class="field span-4">
                    <label for="q">{{ __('Search') }}</label>
                    <div class="search-box">
                        <input id="q" class="input" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Name, number, client…') }}">
                        <button type="submit" aria-label="{{ __('Search') }}"><x-icon name="search" :size="16" /></button>
                    </div>
                </div>
                @includeIf('admin.'.$module->slugName().'._filters')
                <x-select name="region_id" :label="__('Region')" :options="$regions->pluck('name', 'id')" :value="$filters['region_id'] ?? null" placeholder="{{ __('All') }}" span="3" data-region-select />
                <x-select name="district_id" :label="__('District')" :options="$districts" :value="$filters['district_id'] ?? null" placeholder="{{ __('All') }}" span="3" data-district-select />
                <x-select name="appraiser_id" :label="__('Appraiser')" :options="$appraisers" :value="$filters['appraiser_id'] ?? null" placeholder="{{ __('All') }}" span="3" />
                <x-field name="from" type="date" :label="__('From')" :value="$filters['from'] ?? null" span="2" />
                <x-field name="to" type="date" :label="__('To')" :value="$filters['to'] ?? null" span="2" />
                <div class="filter-actions span-2">
                    <button class="btn btn-dark" type="submit"><x-icon name="filter" :size="16" /> {{ __('Filter') }}</button>
                    <a class="btn btn-ghost btn-icon" href="{{ route($module->routeName('index'), $params) }}" title="{{ __('Reset') }}" aria-label="{{ __('Reset') }}"><x-icon name="x" :size="16" /></a>
                </div>
            </div>
            <button type="button" class="btn btn-sm filter-toggle" data-filter-toggle><x-icon name="sliders-horizontal" :size="14" /> {{ __('Filters') }}</button>
        </form>
    </section>

    <section class="card">
        <nav class="tabs" aria-label="{{ __('Status') }}">
            <a href="{{ request()->fullUrlWithQuery(['status' => null, 'page' => null]) }}" @class(['tab', 'is-active' => ! $currentStatus])>{{ __('All') }} <span class="tab-count">{{ $total }}</span></a>
            @foreach ($statuses as $value => $label)
                <a href="{{ request()->fullUrlWithQuery(['status' => $value, 'page' => null]) }}" @class(['tab', 'is-active' => $currentStatus === $value])>{{ $label }} <span class="tab-count">{{ $statusCounts[$value] ?? 0 }}</span></a>
            @endforeach
        </nav>

        <div class="list-toolbar">
            <p class="list-total">{{ __('Total') }}: <strong>{{ $items->total() }}</strong></p>
            <div class="row-flex">
                <a class="btn" href="{{ route($module->routeName('export'), request()->query()) }}"><x-icon name="file-spreadsheet" :size="16" /> {{ __('Excel') }}</a>
                <a class="btn btn-primary" href="{{ route($module->routeName('create'), $params) }}"><x-icon name="plus" :size="16" /> {{ __('Add') }}</a>
            </div>
        </div>

        @if ($items->isEmpty())
            @php $searching = collect(request()->except(['page', 'kind']))->filter(fn ($value) => filled($value))->isNotEmpty(); @endphp
            <x-empty :icon="$module->icon()" :title="$searching ? null : __('No records yet')" :text="$searching ? __('Try changing the filters or add a new record.') : __('Add the first record — it will appear here.')">
                <a class="btn btn-primary" href="{{ route($module->routeName('create'), $params) }}"><x-icon name="plus" :size="16" /> {{ __('Add') }}</a>
            </x-empty>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('Object') }}</th>
                            @foreach ($module->columns() as $heading => $cb)<th>{{ $heading }}</th>@endforeach
                            @if ($module->usesApproaches())<th>{{ __('Approaches') }}</th>@endif
                            <th class="t-right">{{ $module->usesApproaches() ? __('Value') : __('Damage amount') }}</th>
                            <th class="t-center">{{ __('Attachments') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Persons') }}</th>
                            <th class="t-actions"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td class="muted num">{{ $item->id }}</td>
                                <td style="min-width: 200px; max-width: 280px">
                                    <a class="cell-title" href="{{ route($module->routeName('show'), $item->id) }}">{{ $item->title }}</a>
                                    <span class="cell-sub">{{ $item->locationLine() ?: '—' }}</span>
                                    @if ($item->report_number)<span class="cell-sub">{{ __('Report') }} № {{ $item->report_number }}</span>@endif
                                </td>
                                @foreach ($module->columns() as $cb)<td>{{ $cb($item) }}</td>@endforeach
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
                                <td class="t-right cell-num">{{ $item->market_value !== null ? Format::compact($item->market_value) : '—' }}</td>
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
                                <td class="t-actions">
                                    <div class="row-actions">
                                        <a class="btn btn-sm btn-icon" href="{{ route($module->routeName('show'), $item->id) }}" title="{{ __('View') }}" aria-label="{{ __('View') }}"><x-icon name="eye" :size="16" /></a>
                                        <a class="btn btn-sm btn-icon" href="{{ route($module->routeName('edit'), $item->id) }}" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}"><x-icon name="pencil" :size="16" /></a>
                                        <a class="btn btn-sm btn-icon" href="{{ route($module->routeName('print'), $item->id) }}" target="_blank" title="{{ __('Print') }}" aria-label="{{ __('Print') }}"><x-icon name="printer" :size="16" /></a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $items->links() }}
        @endif
    </section>
</x-layouts.admin>
