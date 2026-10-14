<?php

namespace App\Services\Audit;

use App\Models\Activity;
use Illuminate\Support\Facades\Cache;

/**
 * Checks that audit records were not changed or removed after they were
 * written: every record's hash must match its contents, and the record it
 * was chained to must exist. (Records written at the same moment may
 * share a predecessor, so the predecessor is looked up among the recent
 * ones rather than only the one right before.)
 */
class AuditChain
{
    private const WINDOW = 500;

    /** @return array{ok: bool, checked: int, unsigned: int, broken_id: ?int, reason: ?string, checked_at: string} */
    public function verify(): array
    {
        $checked = 0;
        $unsigned = 0;
        $recent = [];
        $first = true;
        $result = null;

        Activity::query()->orderBy('id')->chunkById(1000, function ($records) use (&$checked, &$unsigned, &$recent, &$first, &$result) {
            foreach ($records as $record) {
                if ($record->hash === null) {
                    $unsigned++;

                    continue;
                }
                $checked++;

                if (! hash_equals($record->hash, $record->computedHash())) {
                    $result = ['broken_id' => $record->id, 'reason' => 'The record was changed after it was written.'];

                    return false;
                }
                // The very first signed record may follow records that were pruned.
                if (! $first && $record->prev_hash !== null && ! isset($recent[$record->prev_hash])) {
                    $result = ['broken_id' => $record->id, 'reason' => 'A record before this one was deleted.'];

                    return false;
                }

                $first = false;
                $recent[$record->hash] = true;
                if (count($recent) > self::WINDOW) {
                    array_shift($recent);
                }
            }
        });

        $report = [
            'ok' => $result === null,
            'checked' => $checked,
            'unsigned' => $unsigned,
            'broken_id' => $result['broken_id'] ?? null,
            'reason' => $result['reason'] ?? null,
            'checked_at' => now()->toDateTimeString(),
        ];
        Cache::forever('audit:last_check', $report);

        return $report;
    }

    /** @return array<string, mixed>|null */
    public static function lastCheck(): ?array
    {
        return Cache::get('audit:last_check');
    }
}
