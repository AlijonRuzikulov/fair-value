{{-- "Akt" of the valuation portal: the valued amount, the approaches, the documents to look at (left)
     and the data of the object taken at the inspection (right). Shown in a window, or as a page. --}}
@php
    use App\Enums\ValuationApproach;
    use App\Reports\RealEstateReport;
    use App\Support\Format;
    $choices = fn (string $key, ?array $only = null) => RealEstateReport::choices($key, $rv[$key] ?? null, $only);
    $applied = array_map('strval', (array) old('approaches', $item->approaches ?? []));
    $inWindow = request()->ajax();
    $people = $item->clients->mapWithKeys(fn ($client) => [$client->id => $client->title])->all();
@endphp
<form method="post" action="{{ route('admin.real-estates.act.update', $item->id) }}" class="act-window" data-act-window>
    @csrf @method('put')
    <input type="hidden" name="_back" value="{{ old('_back', $inWindow ? url()->previous() : route('admin.real-estates.show', $item->id)) }}">

    <header class="act-head">
        <strong>{{ __('Valued amount') }}: {{ $item->market_value !== null ? Format::number($item->market_value, 2).' '.__('sum') : '—' }}</strong>
        @if ($inWindow)
            <button type="button" class="btn btn-danger btn-sm" data-act-close>{{ __('Close') }}</button>
        @else
            <a class="btn btn-danger btn-sm" href="{{ old('_back', route('admin.real-estates.show', $item->id)) }}">{{ __('Close') }}</a>
        @endif
    </header>

    <fieldset class="act-body" @disabled(! $canEdit)>
        <div class="act-approaches">
            <input type="hidden" name="approaches[]" value="">
            @foreach ([ValuationApproach::Cost, ValuationApproach::Income, ValuationApproach::Comparative] as $approach)
                <label class="tick"><input type="checkbox" name="approaches[]" value="{{ $approach->value }}" @checked(in_array($approach->value, $applied, true))> <span>{{ $approach->label() }}</span></label>
            @endforeach
            @error('approaches')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="act-grid">
            <div class="act-docs" data-act-carousel>
                <div class="act-stage">
                    @forelse ($documents as $i => $document)
                        <figure class="act-slide" data-act-slide @if ($i) hidden @endif
                                @if ($canEdit && $document->isEditableImage()) data-rotate-url="{{ route('admin.attachments.rotate', $document) }}" @endif>
                            <img src="{{ route('admin.attachments.show', $document) }}" alt="{{ $document->name }}" data-act-image @if ($i) loading="lazy" @endif>
                            <figcaption>{{ $document->documentKind()->label() }} · {{ $document->name }}</figcaption>
                        </figure>
                    @empty
                        <p class="act-empty">{{ __('No pictures of the documents yet: they are added under "Documents" of the object.') }}</p>
                    @endforelse
                    @if ($documents->count() > 1)
                        <button type="button" class="act-nav act-prev" data-act-prev aria-label="{{ __('Previous') }}"><x-icon name="chevron-left" :size="22" /></button>
                        <button type="button" class="act-nav act-next" data-act-next aria-label="{{ __('Next') }}"><x-icon name="chevron-right" :size="22" /></button>
                    @endif
                </div>
                @if ($documents->count() > 1)
                    <div class="act-dots">
                        @foreach ($documents as $i => $document)
                            <button type="button" @class(['is-active' => $i === 0]) data-act-dot="{{ $i }}" aria-label="{{ $i + 1 }}"></button>
                        @endforeach
                    </div>
                @endif
                @if ($canEdit && $documents->contains(fn ($d) => $d->isEditableImage()))
                    <div class="act-rotate">
                        <button type="button" class="btn btn-success btn-sm" data-act-rotate="left" title="{{ __('Turn left') }}" aria-label="{{ __('Turn left') }}"><x-icon name="rotate-ccw" :size="16" /></button>
                        <button type="button" class="btn btn-success btn-sm" data-act-rotate="right" title="{{ __('Turn right') }}" aria-label="{{ __('Turn right') }}"><x-icon name="rotate-cw" :size="16" /></button>
                    </div>
                @endif
            </div>

            <div class="act-fields form-grid">
                @if ($people !== [])
                    <x-select name="owner_id" :label="__('Owner of the property')" :options="$people" :value="$owner?->id" span="12" required />
                @endif
                <x-select name="function" :label="__('Purpose of use')" :options="$choices('function', RealEstateReport::OPTIONS[$residential ? 'function_residential' : 'function_nonresidential'])" :value="$rv['function']" placeholder="" span="12" required />
                <x-select name="architecture" :label="__('Architectural style (facade)')" :options="$choices('architecture')" :value="$rv['architecture']" placeholder="" span="12" required />
                <input type="hidden" name="amenities[]" value="">
                <x-checkboxes name="amenities" :label="__('Special amenities').' *'" :options="RealEstateReport::choicesOf('amenities', $rv['amenities'] ?? [])" :value="$rv['amenities'] ?? []" class="check-list-compact" />
                <x-select name="repair" :label="__('Repair condition')" :options="$choices('repair')" :value="$rv['repair']" placeholder="" span="12" required />
                <x-field name="completion" type="number" :label="__('Construction completed (%)')" :value="number_format((float) $rv['completion'], 2, '.', '')" span="12" required min="0" max="100" step="0.01" />
                <x-select name="ownership_form" :label="__('Form of ownership')" :options="$choices('ownership_form')" :value="$rv['ownership_form'] ?? null" placeholder="" span="12" required />
                <x-select name="land_rights" :label="__('Ownership right to the land plot')" :options="$choices('land_rights')" :value="$rv['land_rights'] ?? null" placeholder="" span="12" required />
                <p class="span-12 act-more">
                    <a href="{{ route('admin.real-estates.report.edit', $item->id) }}#step-inspection"><x-icon name="list" :size="14" /> {{ __('Condition and wear of the structural elements') }}</a>
                </p>
            </div>
        </div>
    </fieldset>

    @if ($canEdit)
        <footer class="act-foot">
            <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> {{ __('Save') }}</button>
        </footer>
    @endif
</form>
