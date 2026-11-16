{{-- The page the bookmark button opens: the listing from the appraiser's browser, added to the chosen object. --}}
@php $bookmarklet = \App\Support\Bookmarklet::analog(route('admin.analogs.capture')); @endphp
<x-layouts.admin :title="__('Add an analogue from the browser')" icon="scale">
    <x-card :title="__('Listing from your browser')" icon="external-link">
        <form method="post" action="{{ route('admin.analogs.captured') }}" class="form-grid capture-form" data-analog-capture hidden>
            @csrf
            <div class="field span-12">
                <span class="field-label">{{ __('Listing') }}</span>
                <p class="capture-listing"><strong data-capture-title></strong><br><span class="cell-sub" data-capture-url></span></p>
                <p class="field-hint" data-capture-facts></p>
            </div>
            @if ($objects->isEmpty())
                <p class="span-12 muted">{{ __('There is no real estate valuation in progress to add it to.') }}</p>
            @else
                <x-select name="object" :label="__('Analogue for the object')" required span="8"
                          :options="$objects->mapWithKeys(fn ($item) => [$item->id => '№'.$item->id.' · '.$item->title])->all()" />
                <div class="field span-4" style="justify-content: flex-end">
                    <button class="btn btn-primary" type="submit" data-busy="{{ __('Reading…') }}"><x-icon name="plus" :size="16" /> {{ __('Add as an analogue') }}</button>
                </div>
            @endif
            <input type="hidden" name="url">
            <input type="hidden" name="title">
            <input type="hidden" name="text">
            <input type="hidden" name="ld">
            <input type="hidden" name="meta">
        </form>

        <div class="capture-help" data-capture-empty>
            <p data-capture-broken hidden class="alert alert-error">{{ __('The listing was not received. Open it in the browser and press the button again.') }}</p>
            <p>{{ __('Listing sites often do not let a server open their pages, but they always open for you. This button sends the listing you are looking at to the system: the price, area, floor and the rest are read from it.') }}</p>
            <ol class="capture-steps">
                <li>{{ __('Drag this button to the bookmarks bar of your browser (Ctrl+Shift+B shows the bar):') }}
                    <a class="btn btn-primary bookmarklet" href="{{ $bookmarklet }}" draggable="true" data-bookmarklet title="{{ __('Drag to the bookmarks bar') }}"><x-icon name="bookmark-plus" :size="16" /> {{ __('+ Analogue') }}</a>
                </li>
                <li>{{ __('Open a listing on any site (OLX, Uybor, Telegram in the browser…).') }}</li>
                <li>{{ __('Press the button in the bookmarks bar, choose the object and press “Add as an analogue”.') }}</li>
            </ol>
            <p class="field-hint">{{ __('If dragging does not work: right-click the bookmarks bar → “Add page…”, name it as you like and paste the address copied with the button below.') }}</p>
            <button type="button" class="btn btn-sm" data-copy="{{ $bookmarklet }}"><x-icon name="copy" :size="14" /> {{ __('Copy the button address') }}</button>
        </div>
    </x-card>
</x-layouts.admin>
