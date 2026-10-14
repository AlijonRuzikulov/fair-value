<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        ], [], ['files' => __('Files'), 'files.*' => __('File')]);

        /** @var class-string<Model> $class */
        $class = Relation::getMorphedModel($data['type']);
        $owner = $class::findOrFail($data['id']);

        foreach ($request->file('files') as $file) {
            $path = $file->storeAs(
                'attachments/'.$data['type'].'/'.$owner->getKey(),
                Str::uuid().'.'.strtolower($file->extension() ?: $file->getClientOriginalExtension()),
                'local'
            );

            $owner->attachments()->create([
                'name' => Str::limit($file->getClientOriginalName(), 250, ''),
                'path' => $path,
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        }

        Activity::record('uploaded', $owner, ['files' => array_map(fn ($file) => $file->getClientOriginalName(), $request->file('files'))]);

        return back()->with('status', trans_choice('{1} File uploaded.|[2,*] :count files uploaded.', count($request->file('files'))));
    }

    public function show(Request $request, Attachment $attachment): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        // Images and PDFs open in the browser, other files are downloaded.
        $inline = ! $request->boolean('download') && ($attachment->isImage() || $attachment->mime === 'application/pdf');
        Activity::record('downloaded', $attachment->attachable, ['file' => $attachment->name]);

        return $inline
            ? Storage::disk('local')->response($attachment->path, $attachment->name)
            : Storage::disk('local')->download($attachment->path, $attachment->name);
    }

    public function destroy(Attachment $attachment): RedirectResponse
    {
        Gate::authorize('delete-records');
        $attachment->delete();
        Activity::record('file_deleted', $attachment->attachable, ['file' => $attachment->name]);

        return back()->with('status', __('File deleted.'));
    }
}
