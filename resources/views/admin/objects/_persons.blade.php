{{-- "Шахслар": clients linked to the object. Rows are added by admin.js. --}}
<div class="persons-editor" data-persons data-search-url="{{ route('admin.clients.search') }}" data-next-index="{{ count($personRows) }}">
    <div data-person-rows class="persons-editor">
        @foreach ($personRows as $i => $row)
            <div class="person-row" data-person-row>
                <div class="combo" data-combo>
                    <input type="hidden" name="persons[{{ $i }}][client_id]" value="{{ $row['client_id'] }}" data-combo-value>
                    <div class="combo-selected" data-combo-selected>
                        <span><strong>{{ $row['title'] }}</strong><small>{{ $row['sub'] }}</small></span>
                        <button type="button" class="btn btn-sm btn-ghost" data-combo-clear>{{ __('Change') }}</button>
                    </div>
                    <input type="search" class="input" data-combo-input placeholder="{{ __('Search by name, passport, TIN or phone…') }}" hidden autocomplete="off">
                </div>
                <select class="input person-role" name="persons[{{ $i }}][role]" aria-label="{{ __('Role') }}">
                    @foreach ($personRoles as $value => $label)
                        <option value="{{ $value }}" @selected($row['role'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="button" class="btn btn-icon btn-danger" data-person-remove title="{{ __('Remove') }}" aria-label="{{ __('Remove') }}"><x-icon name="trash-2" :size="16" /></button>
            </div>
        @endforeach
    </div>

    <template data-person-template>
        <div class="person-row" data-person-row>
            <div class="combo" data-combo>
                <input type="hidden" name="persons[__i__][client_id]" value="" data-combo-value>
                <div class="combo-selected" data-combo-selected hidden>
                    <span><strong></strong><small></small></span>
                    <button type="button" class="btn btn-sm btn-ghost" data-combo-clear>{{ __('Change') }}</button>
                </div>
                <input type="search" class="input" data-combo-input placeholder="{{ __('Search by name, passport, TIN or phone…') }}" autocomplete="off">
            </div>
            <select class="input person-role" name="persons[__i__][role]" aria-label="{{ __('Role') }}">
                @foreach ($personRoles as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <button type="button" class="btn btn-icon btn-danger" data-person-remove title="{{ __('Remove') }}" aria-label="{{ __('Remove') }}"><x-icon name="trash-2" :size="16" /></button>
        </div>
    </template>

    <div class="row-flex">
        <button type="button" class="btn" data-person-add><x-icon name="user-plus" :size="16" /> {{ __('Add person') }}</button>
        <a class="btn btn-ghost" href="{{ route('admin.clients.create', ['type' => 'individual']) }}" target="_blank"><x-icon name="plus" :size="16" /> {{ __('New client') }}</a>
    </div>
    <p class="field-hint" data-combo-empty-text hidden>{{ __('Nothing found') }}</p>
</div>
