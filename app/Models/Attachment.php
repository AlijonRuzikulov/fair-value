<?php

namespace App\Models;

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
    protected $fillable = ['name', 'path', 'mime', 'size', 'uploaded_by'];

    protected static function booted(): void
    {
        static::deleted(fn (Attachment $file) => Storage::disk('local')->delete($file->path));
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
