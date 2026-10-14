<?php

namespace App\Models\Concerns;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Writes create / update / delete events of the model to the activity log.
 */
trait LogsActivity
{
    /** Attributes that are never written to the log. */
    protected static array $activityIgnored = ['updated_at', 'created_at', 'deleted_at', 'remember_token', 'password', 'verify_code'];

    public static function bootLogsActivity(): void
    {
        static::created(fn (Model $model) => Activity::isMuted() || Activity::record('created', $model));

        static::updated(function (Model $model) {
            if (Activity::isMuted()) {
                return;
            }
            $changes = [];
            foreach ($model->getChanges() as $key => $value) {
                if (in_array($key, static::$activityIgnored, true)) {
                    continue;
                }
                $changes[$key] = ['old' => $model->getOriginal($key), 'new' => $value];
            }
            if ($changes !== []) {
                Activity::record('updated', $model, $changes);
            }
        });

        static::deleted(fn (Model $model) => Activity::isMuted() || Activity::record('deleted', $model));
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject')->latest('id');
    }

    /** Short human readable name used in the log. */
    public function activityLabel(): string
    {
        return (string) ($this->title ?? $this->name ?? '#'.$this->getKey());
    }
}
