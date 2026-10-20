<?php

use App\Models\Activity;
use App\Models\User;
use App\Services\Audit\AuditChain;
use App\Services\Audit\SecurityAlert;
use App\Services\CurrencyService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

Artisan::command('currency:fetch', function (CurrencyService $currency) {
    $count = $currency->fetch();
    $this->info("Stored {$count} exchange rates from the Central Bank of Uzbekistan.");
})->purpose('Download official exchange rates from cbu.uz');

Artisan::command('audit:verify', function (AuditChain $chain) {
    $report = $chain->verify();
    if ($report['ok']) {
        $this->info("The audit log is intact: {$report['checked']} records checked.");

        return 0;
    }

    Activity::record('system', null, ['task' => 'audit-verify', 'record' => $report['broken_id'], 'reason' => $report['reason']], 'Audit log integrity check failed');
    SecurityAlert::send('audit-broken', __('The audit log was changed outside the system (record #:id).', ['id' => $report['broken_id']]));
    $this->error("The audit log was changed outside the system at record #{$report['broken_id']}: {$report['reason']}");

    return 1;
})->purpose('Check that audit records were not changed or deleted');

Artisan::command('audit:prune {--days= : Keep this many days (default: AUDIT_RETENTION_DAYS)}', function () {
    $days = max(30, (int) ($this->option('days') ?: config('fairvalue.audit.retention_days')));
    // A mass delete: the per-record guard against deletion does not apply to the retention cleanup.
    $removed = Activity::query()->where('created_at', '<', now()->subDays($days))->delete();
    if ($removed) {
        Activity::record('pruned', null, ['older_than_days' => $days, 'records' => $removed], 'Old audit records removed');
    }
    $this->info("Removed {$removed} audit records older than {$days} days.");
})->purpose('Remove audit records older than the retention period');

Artisan::command('user:password {email? : E-mail of the user (default: ADMIN_EMAIL)} {--password= : The new password; asked for when not given}', function () {
    $email = mb_strtolower(trim((string) ($this->argument('email') ?: config('fairvalue.admin.email'))));
    $user = User::where('email', $email)->first();
    if (! $user) {
        $this->error("There is no user with the e-mail {$email}. Users:");
        $this->table(['E-mail', 'Name', 'Role', 'Active'], User::orderBy('email')->get()->map(fn (User $u) => [$u->email, $u->name, $u->role->value, $u->is_active ? 'yes' : 'no']));

        return 1;
    }

    $password = (string) $this->option('password');
    if ($password === '') {
        $password = (string) $this->secret('New password (at least 10 characters, letters and numbers)');
        if ($password !== (string) $this->secret('The same password again')) {
            $this->error('The passwords do not match.');

            return 1;
        }
    }
    $validator = Validator::make(['password' => $password], ['password' => ['required', Password::defaults()]]);
    if ($validator->fails()) {
        $this->error(implode(' ', $validator->errors()->all()));

        return 1;
    }

    // The account is unlocked; other devices are signed out by auth.session, as after a change in the profile.
    $user->forceFill(['password' => $password, 'is_active' => true, 'remember_token' => Str::random(60)])->save();
    // The hourly limit of sign-in attempts to this account (key as built by the throttle middleware).
    RateLimiter::clear(md5('login'.'login:'.$user->email));
    Activity::record('password_changed', $user, ['reason' => 'Changed in the server terminal'], $user->name);

    $this->info("The password of {$user->email} has been changed. Sign in at ".url('/login'));

    return 0;
})->purpose('Set a new password for a user who cannot sign in');

Schedule::command('currency:fetch')->everyThreeHours()->withoutOverlapping();
Schedule::command('audit:verify')->dailyAt('03:10');
Schedule::command('audit:prune')->dailyAt('03:20');
// Shows on the "System status" page that cron runs.
Schedule::call(fn () => Cache::forever('monitor:scheduler', now()->toDateTimeString()))->everyMinute()->name('heartbeat');
