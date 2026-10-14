<?php

use App\Models\Activity;
use App\Services\Audit\AuditChain;
use App\Services\Audit\SecurityAlert;
use App\Services\CurrencyService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

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

Schedule::command('currency:fetch')->everyThreeHours()->withoutOverlapping();
Schedule::command('audit:verify')->dailyAt('03:10');
Schedule::command('audit:prune')->dailyAt('03:20');
// Shows on the "System status" page that cron runs.
Schedule::call(fn () => Cache::forever('monitor:scheduler', now()->toDateTimeString()))->everyMinute()->name('heartbeat');
