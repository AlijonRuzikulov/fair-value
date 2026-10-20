<?php

namespace App\Models;

use App\Enums\DocumentKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

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
}
