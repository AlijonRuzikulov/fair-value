<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DocumentKind;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Attachment;
use App\Services\Documents\ImageTools;
use App\Support\Registry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Upload, view and delete files attached to clients and valuation objects.
 */
class AttachmentController extends Controller
{
    /** Morph aliases that accept attachments. */
    private const OWNERS = ['client', 'real_estate', 'asset', 'vehicle', 'other_asset', 'damage_case'];

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', self::OWNERS)],
            'id' => ['required', 'integer'],
            'files' => ['required', 'array', 'max:20'],
            'files.*' => ['file', 'max:'.config('fairvalue.uploads.max_kb'), 'mimes:'.config('fairvalue.uploads.mimes')],
            'kind' => ['nullable', Rule::enum(DocumentKind::class)],
        ], [], ['files' => __('Files'), 'files.*' => __('File')]);

        /** @var class-string<Model> $class */
        $class = Relation::getMorphedModel($data['type']);
        $owner = $class::findOrFail($data['id']);
        $this->authorizeChange($owner);

        foreach ($request->file('files') as $file) {
            $path = $file->storeAs(
                'attachments/'.$data['type'].'/'.$owner->getKey(),
                Str::uuid().'.'.strtolower($file->extension() ?: $file->getClientOriginalExtension()),
                'local'
            );

            $name = Str::limit($file->getClientOriginalName(), 250, '');
            $mime = $file->getMimeType();
            $owner->attachments()->create([
                'name' => $name,
                'path' => $path,
                'mime' => $mime,
                'size' => $file->getSize(),
                'kind' => $data['kind'] ?? DocumentKind::guess($name, str_starts_with((string) $mime, 'image/'))->value,
                'uploaded_by' => $request->user()->id,
            ]);
        }

        Activity::record('uploaded', $owner, ['files' => array_map(fn ($file) => $file->getClientOriginalName(), $request->file('files'))]);

        return back()->with('status', trans_choice('{1} File uploaded.|[2,*] :count files uploaded.', count($request->file('files'))));
    }

    public function show(Request $request, Attachment $attachment): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        // A small copy for the lists (not an opening of the file, so not logged).
        if ($request->boolean('thumb') && $attachment->isEditableImage()) {
            try {
                return Storage::disk('local')->response(app(ImageTools::class)->thumbnail($attachment), null, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=604800']);
            } catch (Throwable) {
                // A damaged picture: the original is served below.
            }
        }

        // Images and PDFs open in the browser, other files are downloaded.
        $inline = ! $request->boolean('download') && ($attachment->isImage() || $attachment->mime === 'application/pdf');
        Activity::record('downloaded', $attachment->attachable, ['file' => $attachment->name]);

        return $inline
            ? Storage::disk('local')->response($attachment->path, $attachment->name)
            : Storage::disk('local')->download($attachment->path, $attachment->name);
    }

    /** Turns a picture taken sideways. */
    public function rotate(Request $request, Attachment $attachment, ImageTools $tools): RedirectResponse
    {
        $this->authorizeChange($attachment->attachable);
        $data = $request->validate(['direction' => ['required', 'in:left,right']]);
        abort_unless($attachment->isEditableImage(), 422);

        try {
            $tools->rotate($attachment, $data['direction']);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('The picture could not be turned.'));
        }
        Activity::record('file_changed', $attachment->attachable, ['file' => $attachment->name, 'rotated' => $data['direction'] === 'left' ? '↺ 90°' : '↻ 90°']);

        return back()->with('status', __('The picture has been turned.'));
    }

    /** Says what the file is (passport, cadastral document, photo…). */
    public function update(Request $request, Attachment $attachment): RedirectResponse
    {
        $this->authorizeChange($attachment->attachable);
        $data = $request->validate(['kind' => ['required', Rule::enum(DocumentKind::class)]]);

        $old = $attachment->documentKind();
        $attachment->update(['kind' => $data['kind']]);
        Activity::record('file_changed', $attachment->attachable, ['file' => $attachment->name, 'kind' => ['old' => $old->label(), 'new' => DocumentKind::from($data['kind'])->label()]]);

        return back()->with('status', __('Changes saved.'));
    }

    /** Files of a completed valuation are changed only by those who may change the valuation. */
    private function authorizeChange(?Model $owner): void
    {
        if ($owner && in_array($owner::class, Registry::valuationModels(), true)) {
            Gate::authorize('edit-valuation', $owner);
        }
    }

    public function destroy(Attachment $attachment): RedirectResponse
    {
        Gate::authorize('delete-records');
        $attachment->delete();
        Activity::record('file_deleted', $attachment->attachable, ['file' => $attachment->name]);

        return back()->with('status', __('File deleted.'));
    }
}
