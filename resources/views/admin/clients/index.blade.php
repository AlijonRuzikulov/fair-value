@php use App\Support\Format; use App\Enums\ClientType; @endphp
<x-layouts.admin :title="$type->plural()" :subtitle="__('Clients')" :nav="'clients.'.$type->value" :icon="$type === ClientType::Legal ? 'building-2' : 'user'">
    <section class="card">
        <form method="get" class="card-body" action="{{ route('admin.clients.index') }}">
            <input type="hidden" name="type" value="{{ $type->value }}">
            <div class="filters" data-filters>
                <div class="field span-6">
                    <label for="q">{{ __('Search') }}</label>
                    <div class="search-box">
                        <input id="q" class="input" type="search" name="q" value="{{ request('q') }}"
                               placeholder="{{ $type === ClientType::Legal ? __('Name, TIN, phone…') : __('Full name, passport, PINFL, phone…') }}">
                        <button type="submit" aria-label="{{ __('Search') }}"><x-icon name="search" :size="16" /></button>
                    </div>
                </div>
                <x-select name="region_id" :label="__('Region')" :options="$regions" :value="request('region_id')" placeholder="{{ __('All') }}" span="4" />
                <div class="filter-actions span-2">
                    <button class="btn btn-dark" type="submit"><x-icon name="filter" :size="16" /> {{ __('Filter') }}</button>
                    <a class="btn btn-ghost btn-icon" href="{{ route('admin.clients.index', ['type' => $type->value]) }}" aria-label="{{ __('Reset') }}"><x-icon name="x" :size="16" /></a>
                </div>
            </div>
            <button type="button" class="btn btn-sm filter-toggle" data-filter-toggle><x-icon name="sliders-horizontal" :size="14" /> {{ __('Filters') }}</button>
        </form>
    </section>

    <section class="card">
        <nav class="tabs">
            @foreach (ClientType::cases() as $case)
                <a href="{{ route('admin.clients.index', ['type' => $case->value]) }}" @class(['tab', 'is-active' => $type === $case])>
                    <x-icon :name="$case === ClientType::Legal ? 'building-2' : 'user'" :size="16" /> {{ $case->plural() }}
                </a>
            @endforeach
        </nav>
        <div class="list-toolbar">
            <p class="list-total">{{ __('Total') }}: <strong>{{ $clients->total() }}</strong></p>
            <div class="row-flex">
                <a class="btn" href="{{ route('admin.clients.export', request()->query() + ['type' => $type->value]) }}"><x-icon name="file-spreadsheet" :size="16" /> {{ __('Excel') }}</a>
                <a class="btn btn-primary" href="{{ route('admin.clients.create', ['type' => $type->value]) }}"><x-icon name="plus" :size="16" /> {{ __('Add') }}</a>
            </div>
        </div>

        @if ($clients->isEmpty())
            @php $searching = collect(request()->except(['page', 'type']))->filter(fn ($value) => filled($value))->isNotEmpty(); @endphp
            <x-empty icon="users" :title="$searching ? null : __('No clients yet')" :text="$searching ? __('Try changing the filters or add a new record.') : __('Add the first client — it will appear here.')">
                @unless ($searching)<a class="btn btn-primary" href="{{ route('admin.clients.create', ['type' => $type->value]) }}"><x-icon name="plus" :size="16" /> {{ __('Add') }}</a>@endunless
            </x-empty>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ $type === ClientType::Legal ? __('Company name') : __('Full name') }}</th>
                            <th>{{ $type === ClientType::Legal ? __('TIN') : __('Passport / PINFL') }}</th>
                            <th>{{ __('Phone') }}</th>
                            <th>{{ __('Address') }}</th>
                            <th class="t-center">{{ __('Objects') }}</th>
                            <th class="t-actions"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($clients as $client)
                            @php $objects = $client->real_estates_count + $client->assets_count + $client->vehicles_count + $client->other_assets_count + $client->damage_cases_count; @endphp
                            <tr>
                                <td class="muted num">{{ $client->id }}</td>
                                <td>
                                    <a class="cell-title" href="{{ route('admin.clients.show', $client) }}">{{ $client->title }}</a>
                                    <span class="cell-sub">{{ $type === ClientType::Legal ? ($client->director ? __('Director').': '.$client->director : '') : ($client->birth_date ? Format::date($client->birth_date) : '') }}</span>
                                </td>
                                <td class="num">
                                    @if ($type === ClientType::Legal)
                                        {{ $client->inn ?? '—' }}
                                    @else
                                        {{ $client->document ?? '—' }}
                                        @if ($client->pinfl)<span class="cell-sub">{{ $client->pinfl }}</span>@endif
                                    @endif
                                </td>
                                <td class="nowrap">{{ $client->phone ? Format::phone($client->phone) : '—' }}</td>
                                <td style="max-width: 280px">{{ collect([$client->region?->name, $client->district?->name])->filter()->implode(', ') ?: '—' }}<span class="cell-sub">{{ $client->address }}</span></td>
                                <td class="t-center"><span @class(['badge', 'badge-brand' => $objects, 'badge-muted' => ! $objects])>{{ $objects }}</span></td>
                                <td class="t-actions">
                                    <div class="row-actions">
                                        <a class="btn btn-sm btn-icon" href="{{ route('admin.clients.show', $client) }}" aria-label="{{ __('View') }}" title="{{ __('View') }}"><x-icon name="eye" :size="16" /></a>
                                        <a class="btn btn-sm btn-icon" href="{{ route('admin.clients.edit', $client) }}" aria-label="{{ __('Edit') }}" title="{{ __('Edit') }}"><x-icon name="pencil" :size="16" /></a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $clients->links() }}
        @endif
    </section>
</x-layouts.admin>
