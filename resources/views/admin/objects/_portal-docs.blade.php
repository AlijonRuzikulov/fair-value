{{-- "... baholash uchun taqdim qilinadigan hujjatlar" of the valuation portal: a box for each document, files chosen in it are saved with the page. --}}
@php
    use App\Enums\DocumentKind;
    use App\Support\Format;
    $slots = DocumentKind::portalSlotsFor($item->getMorphClass());
    $byKind = $item->exists ? $item->attachments->sortBy('id')->groupBy(fn ($file) => $file->documentKind()->value) : collect();
    // Files of other kinds (photos, files added before) get a box of their own.
    foreach ($byKind->keys() as $kind) {
        if (! in_array(DocumentKind::from($kind), $slots, true)) {
            $slots[] = DocumentKind::from($kind);
        }
    }
    $accept = '.pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp,.heic,.zip,.rar,.txt';
@endphp
<h3 class="portal-docs-title" id="documents">{{ $module->portalDocumentsTitle() }}</h3>
<div class="portal-docs">
    @foreach ($slots as $kind)
        @php $files = $byKind->get($kind->value, collect()); @endphp
        <div class="field portal-doc">
            <span class="portal-doc-label">{{ $kind->portalLabel() }}</span>
            <label class="portal-drop" data-portal-drop>
                <input type="file" name="documents[{{ $kind->value }}][]" multiple accept="{{ $kind === DocumentKind::Photo ? 'image/*' : $accept }}" data-portal-drop-input aria-label="{{ $kind->portalLabel() }}">
                <span data-portal-drop-text>{{ __('Click here to upload the file!') }}</span>
            </label>
            @error('documents.'.$kind->value.'.*')<p class="field-error">{{ $message }}</p>@enderror
            @if ($files->isNotEmpty())
                <ul class="portal-doc-files">
                    @foreach ($files as $file)
                        <li>
                            <a href="{{ route('admin.attachments.show', $file) }}" target="_blank">{{ $file->name }}</a>
                            <small>{{ Format::bytes($file->size) }}</small>
                            @can('delete-records')
                                <button class="btn btn-sm btn-icon btn-ghost btn-danger" type="submit" form="delete-file-{{ $file->id }}" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}: {{ $file->name }}"><x-icon name="trash-2" :size="14" /></button>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endforeach
</div>
