{{-- What the portal asks for in its other windows, once the vehicle is saved: its data and its value. --}}
@if ($item->exists)
    <details class="portal-fold" id="object-data" data-remember @if (blank($item->brand)) open @endif>
        <summary>{{ __('Data of the vehicle') }}</summary>
        <fieldset class="portal-fields" @disabled(! $canEdit)>
            <div class="form-grid">
                @include('admin.vehicles._fields')
            </div>
        </fieldset>
    </details>
    @include('admin.objects._portal-valuation')
@endif
