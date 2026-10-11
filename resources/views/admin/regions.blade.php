<x-layouts.admin :title="__('Regions and districts')" nav="regions" icon="map-pin">
    <div class="grid grid-side">
        <x-card :title="__('Regions')" icon="map" flush>
            <ul class="mini-list">
                @foreach ($regions as $region)
                    <li @if ($current?->is($region)) style="background: var(--brand-soft)" @endif>
                        <div class="grow"><a href="{{ route('admin.regions.index', ['region' => $region->id]) }}">{{ $region->name }}</a><small>{{ trans_choice(':count district|:count districts', $region->districts_count) }}</small></div>
                    </li>
                @endforeach
            </ul>
            <form method="post" action="{{ route('admin.regions.store') }}" class="card-body stack" style="gap: 10px; border-top: 1px solid var(--line-2)">
                @csrf
                <x-field name="name_uz" :label="__('New region (Latin)')" span="12" required />
                <button class="btn" type="submit"><x-icon name="plus" :size="16" /> {{ __('Add') }}</button>
            </form>
        </x-card>

        @if ($current)
            <div class="stack">
                <x-card :title="$current->name" icon="map-pin">
                    <form method="post" action="{{ route('admin.regions.update', $current) }}" class="form-grid">
                        @csrf @method('put')
                        <x-field name="name_uz" :label="__('Name (Latin)')" :value="$current->name_uz" span="4" required />
                        <x-field name="name_oz" :label="__('Name (Cyrillic)')" :value="$current->name_oz" span="4" />
                        <x-field name="name_ru" :label="__('Name (Russian)')" :value="$current->name_ru" span="4" />
                        <div class="span-12"><button class="btn btn-sm" type="submit">{{ __('Save') }}</button></div>
                    </form>
                </x-card>
                <x-card :title="__('Districts and cities')" icon="list" flush>
                    <div class="table-wrap">
                        <table class="table table-sm">
                            <thead><tr><th>{{ __('Name (Latin)') }}</th><th>{{ __('Name (Cyrillic)') }}</th><th>{{ __('Name (Russian)') }}</th><th class="t-actions"></th></tr></thead>
                            <tbody>
                                @foreach ($current->districts as $district)
                                    <tr>
                                        <td colspan="4" style="padding: 6px 10px">
                                            <form method="post" action="{{ route('admin.districts.update', $district) }}" class="row-flex" style="flex-wrap: nowrap">
                                                @csrf @method('put')
                                                <input class="input" name="name_uz" value="{{ $district->name_uz }}" aria-label="{{ __('Name (Latin)') }}" required>
                                                <input class="input" name="name_oz" value="{{ $district->name_oz }}" aria-label="{{ __('Name (Cyrillic)') }}">
                                                <input class="input" name="name_ru" value="{{ $district->name_ru }}" aria-label="{{ __('Name (Russian)') }}">
                                                <button class="btn btn-sm btn-icon" type="submit" title="{{ __('Save') }}" aria-label="{{ __('Save') }}"><x-icon name="check" :size="16" /></button>
                                                <button class="btn btn-sm btn-icon btn-danger" type="submit" form="del-district-{{ $district->id }}" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}"><x-icon name="trash-2" :size="16" /></button>
                                            </form>
                                            <form id="del-district-{{ $district->id }}" method="post" action="{{ route('admin.districts.destroy', $district) }}" data-confirm="{{ __('Delete this district?') }}">@csrf @method('delete')</form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <form method="post" action="{{ route('admin.regions.districts.store', $current) }}" class="card-body form-grid" style="border-top: 1px solid var(--line-2)">
                        @csrf
                        <x-field name="name_uz" :label="__('New district (Latin)')" span="6" required :hint="__('Cyrillic and Russian names are filled automatically if left empty.')" />
                        <x-field name="name_ru" :label="__('Name (Russian)')" span="4" />
                        <div class="field span-2" style="justify-content: flex-start; padding-top: 22px"><button class="btn" type="submit"><x-icon name="plus" :size="16" /> {{ __('Add') }}</button></div>
                    </form>
                </x-card>
            </div>
        @endif
    </div>
</x-layouts.admin>
