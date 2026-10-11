{{-- "Илова ҳужжатлари": files of a client or a valuation object. --}}
<x-card :title="__('Attachments')" icon="paperclip">
    <form method="post" action="{{ route('admin.attachments.store') }}" enctype="multipart/form-data" data-upload>
        @csrf
        <input type="hidden" name="type" value="{{ $owner->getMorphClass() }}">
        <input type="hidden" name="id" value="{{ $owner->getKey() }}">
        <label class="dropzone" data-dropzone>
            <x-icon name="upload" :size="22" />
            <span><strong>{{ __('Choose files') }}</strong> {{ __('or drag them here') }}</span>
            <span class="faint">PDF, Word, Excel, JPG, PNG · {{ __('up to :size MB', ['size' => intdiv(config('fairvalue.uploads.max_kb'), 1024)]) }}</span>
            <input type="file" name="files[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp,.heic,.zip,.rar,.txt" data-upload-input>
        </label>
        @error('files')<p class="field-error">{{ $message }}</p>@enderror
        @error('files.*')<p class="field-error">{{ $message }}</p>@enderror
    </form>

    @if ($owner->attachments->isNotEmpty())
        <ul class="file-list">
            @foreach ($owner->attachments as $file)
                <li class="file">
                    <span class="file-ext">{{ $file->extension() }}</span>
                    <div class="file-meta">
                        <a href="{{ route('admin.attachments.show', $file) }}" target="_blank">{{ $file->name }}</a>
                        <small>{{ \App\Support\Format::bytes($file->size) }} · {{ \App\Support\Format::datetime($file->created_at) }}@if ($file->uploader) · {{ $file->uploader->name }}@endif</small>
                    </div>
                    <a class="btn btn-sm btn-icon btn-ghost" href="{{ route('admin.attachments.show', [$file, 'download' => 1]) }}" title="{{ __('Download') }}" aria-label="{{ __('Download') }}"><x-icon name="download" :size="16" /></a>
                    @can('delete-records')
                        <form method="post" action="{{ route('admin.attachments.destroy', $file) }}" data-confirm="{{ __('Delete this file?') }}">
                            @csrf @method('delete')
                            <button class="btn btn-sm btn-icon btn-ghost btn-danger" type="submit" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}"><x-icon name="trash-2" :size="16" /></button>
                        </form>
                    @endcan
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
