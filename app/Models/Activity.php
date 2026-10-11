<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Audit log: who created, changed or deleted what and when.
 */
class Activity extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'event', 'subject_type', 'subject_id', 'subject_label', 'changes', 'ip_address'];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    public static function record(string $event, ?Model $subject = null, ?array $changes = null, ?string $label = null): void
    {
        static::create([
            'user_id' => auth()->id(),
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'subject_label' => mb_substr($label ?? ($subject && method_exists($subject, 'activityLabel') ? $subject->activityLabel() : ''), 0, 250),
            'changes' => $changes,
            'ip_address' => request()?->ip(),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    public function eventLabel(): string
    {
        return match ($this->event) {
            'created' => __('Created'),
            'updated' => __('Updated'),
            'deleted' => __('Deleted'),
            'login' => __('Signed in'),
            'logout' => __('Signed out'),
            default => $this->event,
        };
    }
}
