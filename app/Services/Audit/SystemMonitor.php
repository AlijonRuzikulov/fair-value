<?php

namespace App\Services\Audit;

use App\Models\Activity;
use App\Models\CurrencyRate;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The state of the system for the "System status" page: health checks,
 * configuration warnings, recent errors, activity counts and sessions.
 */
class SystemMonitor
{
    private const ERRORS_KEY = 'monitor:errors';

    /** Called for every reported exception (see bootstrap/app.php). */
    public static function recordError(Throwable $e): void
    {
        try {
            $errors = Cache::get(self::ERRORS_KEY, []);
            array_unshift($errors, [
                'time' => now()->toDateTimeString(),
                'type' => class_basename($e),
                'message' => mb_substr($e->getMessage(), 0, 300),
                'where' => basename($e->getFile()).':'.$e->getLine(),
                'url' => app()->runningInConsole() ? 'console' : request()->method().' '.mb_substr(request()->path(), 0, 200),
            ]);
            Cache::forever(self::ERRORS_KEY, array_slice($errors, 0, 30));

            $hour = 'monitor:errors:'.now()->format('YmdH');
            Cache::add($hour, 0, now()->addDays(2));
            $window = 'monitor:errors:w'.intdiv(time(), 600);
            Cache::add($window, 0, now()->addMinutes(20));
            if (Cache::increment($hour) && Cache::increment($window) >= 10) {
                SecurityAlert::send('errors', __('Many errors in the last 10 minutes: :message', ['message' => mb_substr($e->getMessage(), 0, 120)]));
            }
        } catch (Throwable) {
            // Monitoring must never cause a second error.
        }
    }

    /** @return list<array<string, string>> */
    public function recentErrors(): array
    {
        return Cache::get(self::ERRORS_KEY, []);
    }

    public function errorsLast24h(): int
    {
        $total = 0;
        for ($i = 0; $i < 24; $i++) {
            $total += (int) Cache::get('monitor:errors:'.now()->subHours($i)->format('YmdH'), 0);
        }

        return $total;
    }

    /**
     * Health checks and configuration warnings.
     *
     * @return list<array{label: string, status: string, value: string, hint: ?string}>
     */
    public function checks(): array
    {
        $checks = [];
        $add = function (string $label, string $status, string $value, ?string $hint = null) use (&$checks) {
            $checks[] = compact('label', 'status', 'value', 'hint');
        };

        try {
            DB::select('select 1');
            $add(__('Database'), 'ok', config('database.default').' · '.self::bytes($this->databaseSize()));
        } catch (Throwable $e) {
            $add(__('Database'), 'fail', __('No connection'), mb_substr($e->getMessage(), 0, 150));
        }

        $free = @disk_free_space(storage_path());
        $add(__('Free disk space'), $free === false ? 'warn' : ($free < 1024 ** 3 ? 'warn' : 'ok'), $free === false ? '—' : self::bytes($free),
            $free !== false && $free < 1024 ** 3 ? __('Less than 1 GB left: uploaded files and the database may stop being saved.') : null);

        $writable = is_writable(storage_path('app')) && is_writable(storage_path('logs')) && is_writable(base_path('bootstrap/cache'));
        $add(__('Storage folders'), $writable ? 'ok' : 'fail', $writable ? __('writable') : __('not writable'),
            $writable ? null : __('Give the web server write access to storage/ and bootstrap/cache/.'));

        $heartbeat = Cache::get('monitor:scheduler');
        $late = ! $heartbeat || Carbon::parse($heartbeat)->lt(now()->subMinutes(5));
        $add(__('Scheduled tasks (cron)'), $late ? 'warn' : 'ok', $heartbeat ? Carbon::parse($heartbeat)->diffForHumans() : __('never ran'),
            $late ? __('Add the cron line from the README: rates, audit checks and cleanup run from it.') : null);

        $rateDate = CurrencyRate::max('rate_date');
        $stale = ! $rateDate || Carbon::parse($rateDate)->lt(now()->subDays(4));
        $add(__('Exchange rates'), $stale ? 'warn' : 'ok', $rateDate ? Carbon::parse($rateDate)->format('d.m.Y') : __('not loaded'));

        if (Schema::hasTable('failed_jobs')) {
            $failed = DB::table('failed_jobs')->count();
            $add(__('Failed background tasks'), $failed ? 'warn' : 'ok', (string) $failed);
        }

        $errors = $this->errorsLast24h();
        $add(__('Errors in 24 hours'), $errors > 20 ? 'fail' : ($errors > 0 ? 'warn' : 'ok'), (string) $errors);

        $audit = AuditChain::lastCheck();
        $add(__('Audit log integrity'), $audit === null ? 'warn' : ($audit['ok'] ? 'ok' : 'fail'),
            $audit === null ? __('not checked yet') : ($audit['ok'] ? __(':count records intact', ['count' => $audit['checked']]) : __('changed outside the system (record #:id)', ['id' => $audit['broken_id']])),
            $audit ? __('Checked: :date', ['date' => $audit['checked_at']]) : null);

        // Configuration of a public site.
        $add(__('Debug mode'), config('app.debug') ? 'fail' : 'ok', config('app.debug') ? __('on') : __('off'),
            config('app.debug') ? __('APP_DEBUG=true shows error details to visitors. Set APP_DEBUG=false.') : null);
        $add(__('Environment'), app()->isProduction() ? 'ok' : 'warn', (string) config('app.env'),
            app()->isProduction() ? null : __('On the real site set APP_ENV=production.'));
        $https = str_starts_with((string) config('app.url'), 'https://');
        $add(__('HTTPS'), $https ? 'ok' : 'warn', (string) config('app.url'), $https ? null : __('Serve the site over https and set APP_URL=https://….'));
        $add(__('Secure session cookie'), config('session.secure') ? 'ok' : 'warn', config('session.secure') ? __('on') : __('off'),
            config('session.secure') ? null : __('Set SESSION_SECURE_COOKIE=true on an https site.'));
        $add(__('Demo mode'), config('fairvalue.demo') ? 'warn' : 'ok', config('fairvalue.demo') ? __('on') : __('off'),
            config('fairvalue.demo') ? __('The sign-in page shows the first login. Set DEMO_MODE=false on the real site.') : null);

        $initial = (string) config('fairvalue.admin.password');
        $first = $initial !== '' ? User::where('email', config('fairvalue.admin.email'))->where('is_active', true)->first() : null;
        $weak = $first && Hash::check($initial, $first->password);
        $add(__('Initial password'), $weak ? 'fail' : 'ok', $weak ? __('still in use') : __('changed'),
            $weak ? __('An account still signs in with ADMIN_PASSWORD from .env. Change it in the profile.') : null);

        $telegram = config('services.telegram.bot_token') && config('services.telegram.chat_id');
        $add(__('Security alerts to Telegram'), $telegram ? 'ok' : 'warn', $telegram ? __('on') : __('off'),
            $telegram ? null : __('Set TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID to receive alerts.'));

        return $checks;
    }

    /** @return array<string, array{day: int, week: int}> */
    public function stats(): array
    {
        $events = ['login', 'login_failed', 'login_locked', 'created', 'updated', 'deleted', 'exported', 'downloaded', 'printed', 'viewed', 'verified'];
        $rows = Activity::query()
            ->whereIn('event', $events)
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('event, sum(case when created_at >= ? then 1 else 0 end) as day, count(*) as week', [now()->subDay()->toDateTimeString()])
            ->groupBy('event')
            ->get()
            ->keyBy('event');

        return collect($events)->mapWithKeys(fn ($event) => [$event => [
            'day' => (int) ($rows[$event]->day ?? 0),
            'week' => (int) ($rows[$event]->week ?? 0),
        ]])->all();
    }

    /** Signed-in sessions (only with SESSION_DRIVER=database). */
    public function sessions(): ?Collection
    {
        if (config('session.driver') !== 'database' || ! Schema::hasTable('sessions')) {
            return null;
        }

        $lifetime = (int) config('session.lifetime');
        $sessions = DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', now()->subMinutes($lifetime)->getTimestamp())
            ->orderByDesc('last_activity')
            ->limit(100)
            ->get(['id', 'user_id', 'ip_address', 'user_agent', 'last_activity']);
        $users = User::whereIn('id', $sessions->pluck('user_id'))->get()->keyBy('id');

        return $sessions->map(fn ($s) => (object) [
            'id' => $s->id,
            'user' => $users[$s->user_id] ?? null,
            'ip' => $s->ip_address,
            'device' => (new Activity(['user_agent' => $s->user_agent]))->deviceLabel(),
            'last_activity' => Carbon::createFromTimestamp($s->last_activity),
        ]);
    }

    private function databaseSize(): int
    {
        try {
            if (config('database.default') === 'sqlite') {
                $path = config('database.connections.sqlite.database');

                return is_file($path) ? (int) filesize($path) : 0;
            }
            if (in_array(config('database.default'), ['mysql', 'mariadb'], true)) {
                return (int) DB::selectOne('select sum(data_length + index_length) as size from information_schema.tables where table_schema = database()')->size;
            }
        } catch (Throwable) {
        }

        return 0;
    }

    public static function bytes(int|float $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $unit) {
            if ($bytes < 1024) {
                return round($bytes, 1).' '.$unit;
            }
            $bytes /= 1024;
        }

        return round($bytes, 1).' PB';
    }
}
