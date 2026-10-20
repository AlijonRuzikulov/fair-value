{{-- Documents of a valuation object, each kind in its own place: what is there, what is missing; pictures can be turned. --}}
@php
    use App\Enums\DocumentKind;
    $type = $owner->getMorphClass();
    $slots = DocumentKind::slotsFor($type);
    $required = DocumentKind::requiredFor($type);
    $byKind = $owner->attachments->sortBy('id')->groupBy(fn ($file) => in_array($file->documentKind(), $slots, true) ? $file->documentKind()->value : DocumentKind::Other->value);
    $missing = collect($required)->reject(fn ($kind) => $byKind->has($kind->value));
    $canChange = \Illuminate\Support\Facades\Gate::allows('edit-valuation', $owner);
    $kindOptions = collect($slots)->mapWithKeys(fn ($kind) => [$kind->value => $kind->label()]);
    $accept = '.pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp,.heic,.zip,.rar,.txt';
@endphp
<x-card :title="__('Documents for the valuation')" icon="folder-open">
    <x-slot:actions>
        @if ($missing->isEmpty())
            <span class="badge badge-success"><x-icon name="check" :size="12" /> {{ __('All the documents are here') }}</span>
        @else
            <span class="badge badge-warning" title="{{ $missing->map->label()->implode(', ') }}">{{ __('Missing: :count', ['count' => $missing->count()]) }}</span>
        @endif
    </x-slot:actions>

    @if ($canChange)
        <form method="post" action="{{ route('admin.attachments.store') }}" enctype="multipart/form-data" data-upload>
            @csrf
            <input type="hidden" name="type" value="{{ $type }}">
            <input type="hidden" name="id" value="{{ $owner->getKey() }}">
            <label class="dropzone dropzone-sm" data-dropzone>
                <x-icon name="upload" :size="20" />
                <span><strong>{{ __('Choose files') }}</strong> {{ __('or drag them here') }} — {{ __('the kind is told by the file name and can be changed') }}</span>
                <input type="file" name="files[]" multiple accept="{{ $accept }}" data-upload-input>
            </label>
            @error('files')<p class="field-error">{{ $message }}</p>@enderror
            @error('files.*')<p class="field-error">{{ $message }}</p>@enderror
        </form>
    @endif

    <div class="doc-slots">
        @foreach ($slots as $kind)
            @php $files = $byKind->get($kind->value, collect()); $isMissing = $missing->contains($kind); @endphp
            <section @class(['doc-slot', 'is-wide' => in_array($kind, [DocumentKind::Photo, DocumentKind::Other], true), 'is-missing' => $isMissing])>
                <header class="doc-slot-head">
                    <span class="doc-slot-icon"><x-icon :name="$kind->icon()" :size="16" /></span>
                    <strong>{{ $kind->label() }}</strong>
                    @if ($files->isNotEmpty())<span class="chip">{{ $files->count() }}</span>@endif
                    @if ($isMissing)<span class="badge badge-warning">{{ __('Missing') }}</span>@endif
                    @if ($canChange)
                        <form method="post" action="{{ route('admin.attachments.store') }}" enctype="multipart/form-data" class="doc-slot-add">
                            @csrf
                            <input type="hidden" name="type" value="{{ $type }}">
                            <input type="hidden" name="id" value="{{ $owner->getKey() }}">
                            <input type="hidden" name="kind" value="{{ $kind->value }}">
                            <label class="btn btn-sm btn-ghost">
                                <x-icon name="plus" :size="14" /> {{ __('Add') }}
                                <input type="file" name="files[]" multiple accept="{{ $kind === DocumentKind::Photo ? 'image/*' : $accept }}" @if ($kind === DocumentKind::Photo) capture="environment" @endif class="sr-only" data-submit-on-change>
                            </label>
                        </form>
                    @endif
                </header>

                @if ($files->isEmpty())
                    <p class="doc-slot-empty">{{ __('No files yet') }}</p>
                @else
                    <div class="doc-thumbs">
                        @foreach ($files as $file)
                            <figure class="doc-thumb">
                                <a class="doc-thumb-view" href="{{ route('admin.attachments.show', $file) }}" target="_blank" title="{{ $file->name }}">
                                    @if ($file->isEditableImage())
                                        <img src="{{ route('admin.attachments.show', [$file, 'thumb' => 1, 'v' => $file->updated_at?->timestamp]) }}" alt="{{ $file->name }}" loading="lazy">
                                    @else
                                        <span class="doc-thumb-file">{{ $file->extension() }}</span>
                                    @endif
                                </a>
                                <figcaption>
                                    <span class="doc-thumb-name" title="{{ $file->name }}">{{ $file->name }}</span>
                                    <small>{{ \App\Support\Format::bytes($file->size) }}@if ($file->uploader) · {{ $file->uploader->name }}@endif</small>
                                </figcaption>
                                <div class="doc-thumb-tools">
                                    @if ($canChange && $file->isEditableImage())
                                        @foreach (['left' => 'rotate-ccw', 'right' => 'rotate-cw'] as $direction => $icon)
                                            <form method="post" action="{{ route('admin.attachments.rotate', $file) }}">
                                                @csrf
                                                <input type="hidden" name="direction" value="{{ $direction }}">
                                                <button class="btn btn-sm btn-icon btn-ghost" type="submit" title="{{ $direction === 'left' ? __('Turn left') : __('Turn right') }}" aria-label="{{ $direction === 'left' ? __('Turn left') : __('Turn right') }}"><x-icon :name="$icon" :size="15" /></button>
                                            </form>
                                        @endforeach
                                    @endif
                                    <a class="btn btn-sm btn-icon btn-ghost" href="{{ route('admin.attachments.show', [$file, 'download' => 1]) }}" title="{{ __('Download') }}" aria-label="{{ __('Download') }}"><x-icon name="download" :size="15" /></a>
                                    @can('delete-records')
                                        <form method="post" action="{{ route('admin.attachments.destroy', $file) }}" data-confirm="{{ __('Delete this file?') }}">
                                            @csrf @method('delete')
                                            <button class="btn btn-sm btn-icon btn-ghost btn-danger" type="submit" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}"><x-icon name="trash-2" :size="15" /></button>
                                        </form>
                                    @endcan
                                    @if ($canChange)
                                        <form method="post" action="{{ route('admin.attachments.update', $file) }}" class="doc-thumb-kind">
                                            @csrf @method('patch')
                                            <select name="kind" class="input input-sm" aria-label="{{ __('What the file is') }}" data-submit-on-change>
                                                @foreach ($kindOptions as $value => $label)
                                                    <option value="{{ $value }}" @selected($value === $kind->value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </form>
                                    @endif
                                </div>
                            </figure>
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach
    </div>
</x-card>
