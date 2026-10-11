<x-layouts.admin :title="$module->title()" :subtitle="__('Damage caused')" :nav="$module->nav()" :icon="$module->icon()">
    <section class="card">
        <form method="get" class="card-body" action="{{ route($module->routeName('index')) }}">
            <div class="filters" data-filters>
                <div class="field span-6">
                    <label for="q">{{ __('Search') }}</label>
                    <div class="search-box">
                        <input id="q" class="input" type="search" name="q" value="{{ request('q') }}">
                        <button type="submit" aria-label="{{ __('Search') }}"><x-icon name="search" :size="16" /></button>
                    </div>
                </div>
                @if ($module->hasCarModel())
                    <x-select name="car_model_id" :label="__('Car model')" :options="$carModels->pluck('title', 'id')" :value="request('car_model_id')" placeholder="{{ __('All') }}" span="3" />
                @endif
                @if ($module instanceof \App\Http\Controllers\Admin\RepairNormController)
                    <x-select name="operation" :label="__('Operation')" :options="\App\Enums\RepairOperation::options()" :value="request('operation')" placeholder="{{ __('All') }}" span="3" />
                @endif
                <div class="filter-actions span-3">
                    <button class="btn btn-dark" type="submit"><x-icon name="filter" :size="16" /> {{ __('Filter') }}</button>
                    <a class="btn btn-ghost btn-icon" href="{{ route($module->routeName('index')) }}" aria-label="{{ __('Reset') }}"><x-icon name="x" :size="16" /></a>
                </div>
            </div>
            <button type="button" class="btn btn-sm filter-toggle" data-filter-toggle><x-icon name="sliders-horizontal" :size="14" /> {{ __('Filters') }}</button>
            @if ($module->description())<p class="field-hint" style="margin-top: 12px">{{ $module->description() }}</p>@endif
        </form>
    </section>

    <section class="card">
        <div class="list-toolbar">
            <p class="list-total">{{ __('Total') }}: <strong>{{ $items->total() }}</strong></p>
            <div class="row-flex">
                @can('manage-catalogs')
                    <details class="dropdown">
                        <summary class="btn"><x-icon name="upload" :size="16" /> {{ __('Import') }}</summary>
                        <div class="dropdown-menu dropdown-right" style="width: 320px; padding: 14px">
                            <form method="post" action="{{ route($module->routeName('import')) }}" enctype="multipart/form-data" class="stack" style="gap: 10px">
                                @csrf
                                <p class="field-hint">{{ __('CSV file (Excel → Save as → CSV) with the columns:') }} <strong>{{ implode('; ', $module->csvHeadings()) }}</strong>. {{ __('The first row is a header.') }}</p>
                                <input type="file" name="file" accept=".csv,.txt" required class="input" style="padding-top: 8px">
                                @error('file')<p class="field-error">{{ $message }}</p>@enderror
                                <button class="btn btn-primary" type="submit">{{ __('Import') }}</button>
                            </form>
                        </div>
                    </details>
                @endcan
                <a class="btn" href="{{ route($module->routeName('export'), request()->query()) }}"><x-icon name="file-spreadsheet" :size="16" /> {{ __('Excel') }}</a>
                @can('manage-catalogs')
                    <a class="btn btn-primary" href="{{ route($module->routeName('create')) }}"><x-icon name="plus" :size="16" /> {{ __('Add') }}</a>
                @endcan
            </div>
        </div>

        @if ($items->isEmpty())
            <x-empty :icon="$module->icon()" :text="__('Add records one by one or import a price list from Excel.')" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            @foreach ($module->columns() as $heading => $cb)<th>{{ $heading }}</th>@endforeach
                            <th class="t-actions"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                @foreach ($module->columns() as $cb)
                                    <td @class(['cell-title' => $loop->first])>{{ $cb($item) }}</td>
                                @endforeach
                                <td class="t-actions">
                                    @can('manage-catalogs')
                                        <div class="row-actions">
                                            <a class="btn btn-sm btn-icon" href="{{ route($module->routeName('edit'), $item->id) }}" aria-label="{{ __('Edit') }}" title="{{ __('Edit') }}"><x-icon name="pencil" :size="16" /></a>
                                            <form method="post" action="{{ route($module->routeName('destroy'), $item->id) }}" data-confirm="{{ __('Delete this record?') }}">
                                                @csrf @method('delete')
                                                <button class="btn btn-sm btn-icon btn-danger" type="submit" aria-label="{{ __('Delete') }}" title="{{ __('Delete') }}"><x-icon name="trash-2" :size="16" /></button>
                                            </form>
                                        </div>
                                    @endcan
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
