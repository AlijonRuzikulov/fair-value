<?php

namespace App\Models;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Cache;
use LogicException;

/**
 * Audit log: who signed in and out, what was entered, changed, deleted,
 * exported, downloaded or printed, and what the system itself did.
 *
 * Records are never changed or deleted by the application (only pruned
 * after the retention period), and each record carries a hash of its
 * contents and of the previous record, so that a change made directly
 * in the database is detected by "php artisan audit:verify".
 */
class Activity extends Model
{
    public const UPDATED_AT = null;

    /** Event => category (auth: signing in and out; data: entered; access: taken out; system). */
    public const EVENTS = [
        'login' => 'auth',
        'logout' => 'auth',
        'login_failed' => 'auth',
        'login_locked' => 'auth',
        'session_ended' => 'auth',
        'password_changed' => 'auth',
        'created' => 'data',
        'updated' => 'data',
        'deleted' => 'data',
        'imported' => 'data',
        'uploaded' => 'data',
        'file_deleted' => 'data',
        'file_changed' => 'data',
        'settings' => 'data',
        'analogs' => 'data',
        'documents_read' => 'data',
        'viewed' => 'access',
        'exported' => 'access',
        'downloaded' => 'access',
        'printed' => 'access',
        'verified' => 'access',
        'system' => 'system',
        'pruned' => 'system',
    ];

    public const CATEGORIES = ['auth', 'data', 'access', 'system'];

    /** Model events are not logged while this is set (bulk imports log one summary). */
    private static bool $muted = false;

    protected $fillable = [
        'user_id', 'user_email', 'event', 'category', 'subject_type', 'subject_id', 'subject_label',
        'changes', 'ip_address', 'user_agent', 'url', 'prev_hash', 'hash', 'created_at',
    ];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit records cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Audit records cannot be deleted.'));
    }

    public static function record(
        string $event,
        ?Model $subject = null,
        ?array $changes = null,
        ?string $label = null,
        ?User $user = null,
        ?string $email = null,
    ): ?self {
        $user ??= auth()->user();
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        $attributes = [
            'created_at' => now()->toDateTimeString(),
            'user_id' => $user?->getKey(),
            'user_email' => $email ?? $user?->email,
            'event' => $event,
            'category' => self::EVENTS[$event] ?? 'data',
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'subject_label' => mb_substr($label ?? ($subject && method_exists($subject, 'activityLabel') ? $subject->activityLabel() : ''), 0, 250),
            'changes' => $changes,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
            'url' => $request ? mb_substr($request->method().' '.$request->getRequestUri(), 0, 500) : 'console',
        ];

        $write = function () use ($attributes) {
            $attributes['prev_hash'] = static::query()->latest('id')->value('hash');
            $attributes['hash'] = self::hashOf($attributes);

            return static::create($attributes);
        };

        try {
            return Cache::lock('audit-chain', 10)->block(5, $write);
        } catch (LockTimeoutException) {
            // A record is never lost because of a busy lock.
            return $write();
        }
    }

    /** A page with personal data was opened; logged once per employee and record every 30 minutes. */
    public static function recordView(Model $subject): void
    {
        $key = 'audit:view:'.auth()->id().':'.$subject->getMorphClass().':'.$subject->getKey();
        if (Cache::add($key, true, now()->addMinutes(30))) {
            self::record('viewed', $subject);
        }
    }

    /** Runs $callback without logging model events; returns its result. */
    public static function muted(callable $callback): mixed
    {
        $previous = self::$muted;
        self::$muted = true;
        try {
            return $callback();
        } finally {
            self::$muted = $previous;
        }
    }

    public static function isMuted(): bool
    {
        return self::$muted;
    }

    /** Hash of the record's contents chained to the previous record. */
    public static function hashOf(array $a): string
    {
        $fields = [];
        foreach (['created_at', 'user_id', 'user_email', 'event', 'category', 'subject_type', 'subject_id', 'subject_label', 'ip_address', 'user_agent', 'url'] as $key) {
            $value = $a[$key] ?? null;
            $fields[$key] = $value === null || $value === '' ? null : (string) $value;
        }
        $fields['changes'] = self::canonical($a['changes'] ?? null);

        return hash('sha256', ($a['prev_hash'] ?? '').'|'.json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    }

    /** Sorted keys, so that a database which reorders JSON keys does not break the hash. */
    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map(fn ($item) => self::canonical($item), $value);
    }

    public function computedHash(): string
    {
        return self::hashOf([
            ...$this->getAttributes(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            // getAttribute(): inside the model "$this->changes" is Eloquent's own list of changed attributes.
            'changes' => $this->getAttribute('changes'),
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

    /**
     * The record's name. System entries store an English text so the log
     * reads in each viewer's own language.
     */
    public function subjectText(): string
    {
        $label = (string) $this->subject_label;

        return $this->subject_type === null ? __($label) : $label;
    }

    public function eventLabel(): string
    {
        return match ($this->event) {
            'created' => __('Created'),
            'updated' => __('Updated'),
            'deleted' => __('Deleted'),
            'login' => __('Signed in'),
            'logout' => __('Signed out'),
            'login_failed' => __('Failed sign-in'),
            'login_locked' => __('Sign-in blocked'),
            'session_ended' => __('Session ended'),
            'password_changed' => __('Password changed'),
            'imported' => __('Imported'),
            'uploaded' => __('Files uploaded'),
            'file_deleted' => __('File deleted'),
            'file_changed' => __('File changed'),
            'settings' => __('Settings changed'),
            'analogs' => __('Analogues'),
            'documents_read' => __('Documents read'),
            'viewed' => __('Viewed'),
            'exported' => __('Exported'),
            'downloaded' => __('File opened'),
            'printed' => __('Printed'),
            'verified' => __('Report checked'),
            'system' => __('System'),
            'pruned' => __('Old records removed'),
            default => $this->event,
        };
    }

    public static function categoryLabel(string $category): string
    {
        return match ($category) {
            'auth' => __('Sign-in and sign-out'),
            'data' => __('Data entered'),
            'access' => __('Data taken out'),
            'system' => __('System'),
            default => $category,
        };
    }

    /** Who did it: an employee, an address that tried to sign in, a website visitor or the system. */
    public function actorLabel(): string
    {
        // Only an eagerly loaded user: lists show the e-mail saved with the record otherwise.
        $user = $this->relationLoaded('user') ? $this->user : null;
        if ($user) {
            return $user->name;
        }
        if ($this->user_email) {
            return $this->user_email;
        }
        if ($this->category === 'system') {
            return __('System');
        }
        if ($this->url && $this->url !== 'console' && ! str_contains($this->url, ' /admin')) {
            return __('Website visitor');
        }

        return __('System');
    }

    /** "Chrome · Windows" from the browser's user agent. */
    public function deviceLabel(): ?string
    {
        $ua = (string) $this->user_agent;
        if ($ua === '') {
            return null;
        }
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'YaBrowser') => 'Yandex',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') => 'Safari',
            default => null,
        };
        $os = match (true) {
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => null,
        };

        return implode(' · ', array_filter([$browser, $os])) ?: mb_substr($ua, 0, 40);
    }
}
