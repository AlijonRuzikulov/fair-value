<?php

namespace App\Models;

use App\Enums\DocumentKind;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * "Илова ҳужжатлари" — a file attached to a client or a valuation object.
 * Files live on the private disk and are served only to signed-in users.
 */
class Attachment extends Model
{
    protected $fillable = ['name', 'path', 'mime', 'size', 'kind', 'uploaded_by'];

    protected static function booted(): void
    {
        static::deleted(function (Attachment $file) {
            Storage::disk('local')->delete($file->path);
            Storage::disk('local')->delete(Storage::disk('local')->files('thumbs/'.$file->id));
        });
    }

    /** Keeps an uploaded file on the private disk, attached to its owner; without a kind it is told by the file name. */
    public static function keep(Model $owner, UploadedFile $file, ?string $kind, int $userId): self
    {
        $type = $owner->getMorphClass();
        $path = $file->storeAs(
            'attachments/'.$type.'/'.$owner->getKey(),
            Str::uuid().'.'.strtolower($file->extension() ?: $file->getClientOriginalExtension()),
            'local'
        );
        $name = Str::limit($file->getClientOriginalName(), 250, '');
        $mime = $file->getMimeType();

        return $owner->attachments()->create([
            'name' => $name,
            'path' => $path,
            'mime' => $mime,
            'size' => $file->getSize(),
            'kind' => $kind ?? DocumentKind::guess($name, str_starts_with((string) $mime, 'image/'))->value,
            'uploaded_by' => $userId,
        ]);
    }

    /** What the file is: as chosen, or told by its name. */
    public function documentKind(): DocumentKind
    {
        return DocumentKind::tryFrom((string) $this->kind) ?? DocumentKind::guess((string) $this->name, $this->isImage());
    }

    /** Pictures that can be turned and made small (HEIC is not read by GD). */
    public function isEditableImage(): bool
    {
        return in_array($this->mime, ['image/jpeg', 'image/png', 'image/webp'], true);
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function extension(): string
    {
        return strtoupper(pathinfo($this->name, PATHINFO_EXTENSION) ?: 'FILE');
    }

    /** A record of another workspace is not found. */
    public function resolveRouteBinding($value, $field = null)
    {
        $attachment = parent::resolveRouteBinding($value, $field);

        return $attachment && Tenant::owns($attachment->attachable_type, $attachment->attachable_id) ? $attachment : null;
    }
}
