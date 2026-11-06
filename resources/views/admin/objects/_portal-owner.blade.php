{{-- The owner of the property as on the valuation portal: chosen on its own; the other persons (by power of attorney, another organisation) under the tick. --}}
@php
    $ownerRow = collect($personRows)->firstWhere('role', \App\Enums\PersonRole::Owner->value);
    $otherRows = collect($personRows)->reject(fn ($row) => $row === $ownerRow)->values()->all();
@endphp
<div class="field span-12">
    <label for="f-owner">{{ __('Owner of the property') }}<span class="req">*</span></label>
    <div class="combo" data-client-pick data-search-url="{{ route('admin.clients.search') }}">
        <input type="hidden" name="owner_id" value="{{ $ownerRow['client_id'] ?? '' }}" data-combo-value>
        <div class="combo-selected" data-combo-selected @if (! $ownerRow) hidden @endif>
            <span><strong>{{ $ownerRow['title'] ?? '' }}</strong><small>{{ $ownerRow['sub'] ?? '' }}</small></span>
            <button type="button" class="btn btn-sm btn-ghost" data-combo-clear>{{ __('Change') }}</button>
        </div>
        <input id="f-owner" type="search" @class(['input', 'is-invalid' => $errors->has('owner_id')]) data-combo-input placeholder="{{ __('Search by name, passport, TIN or phone…') }}" autocomplete="off" @if ($ownerRow) hidden @endif>
    </div>
    @error('owner_id')<p class="field-error">{{ $message }}</p>@enderror
    <label class="tick"><input type="checkbox" data-others-toggle @checked($otherRows !== [])> <span>{{ __('By power of attorney / is an order of another organisation involved') }}</span></label>
</div>
<div class="field span-12" data-others @if ($otherRows === []) hidden @endif>
    @include('admin.objects._persons', ['personRows' => $otherRows])
</div>
