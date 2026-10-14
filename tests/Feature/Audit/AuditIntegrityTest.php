<?php

namespace Tests\Feature\Audit;

use App\Models\Activity;
use App\Models\User;
use App\Services\Audit\AuditChain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

/**
 * Audit records cannot be changed through the application, and changes
 * made directly in the database are detected.
 */
class AuditIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function fill(int $count): void
    {
        $user = User::factory()->create();
        for ($i = 1; $i <= $count; $i++) {
            Activity::record('updated', $user, ['name' => ['old' => "a{$i}", 'new' => "b{$i}"], 'size' => 1.5, 'тест' => 'ўзбек'], "Record {$i}", $user);
        }
    }

    public function test_records_are_chained_and_verified(): void
    {
        $this->fill(5);

        $records = Activity::orderBy('id')->get();
        $this->assertNull($records[0]->prev_hash);
        $this->assertSame($records[0]->hash, $records[1]->prev_hash);

        $report = app(AuditChain::class)->verify();
        $this->assertTrue($report['ok']);
        $this->assertSame(5, $report['checked']);
        $this->artisan('audit:verify')->assertExitCode(0);
    }

    public function test_the_application_cannot_change_or_delete_records(): void
    {
        $this->fill(1);
        $record = Activity::first();

        $this->expectException(LogicException::class);
        $record->update(['subject_label' => 'forged']);
    }

    public function test_the_application_cannot_delete_a_record(): void
    {
        $this->fill(1);

        $this->expectException(LogicException::class);
        Activity::first()->delete();
    }

    public function test_a_change_in_the_database_is_detected(): void
    {
        $this->fill(4);
        $target = Activity::orderBy('id')->skip(2)->first();
        DB::table('activities')->where('id', $target->id)->update(['changes' => json_encode(['name' => ['old' => 'a3', 'new' => 'forged']])]);

        $report = app(AuditChain::class)->verify();

        $this->assertFalse($report['ok']);
        $this->assertSame($target->id, $report['broken_id']);
        $this->artisan('audit:verify')->assertExitCode(1);
        $this->assertSame('audit-verify', Activity::where('event', 'system')->latest('id')->first()->changes['task']);
    }

    public function test_a_deleted_record_is_detected(): void
    {
        $this->fill(4);
        $ids = Activity::orderBy('id')->pluck('id');
        DB::table('activities')->where('id', $ids[1])->delete();

        $report = app(AuditChain::class)->verify();

        $this->assertFalse($report['ok']);
        $this->assertSame($ids[2], $report['broken_id']);
    }

    public function test_old_records_are_pruned_and_the_rest_still_verifies(): void
    {
        $this->fill(3);
        DB::table('activities')->whereIn('id', Activity::orderBy('id')->limit(2)->pluck('id'))->update(['created_at' => now()->subYears(4)]);
        // Changing created_at above broke those two records; they are the ones removed now.

        $this->artisan('audit:prune', ['--days' => 365])->assertExitCode(0);

        $this->assertSame(2, Activity::count(), 'One record kept and one record about the cleanup.');
        $this->assertSame(2, Activity::where('event', 'pruned')->first()->changes['records']);
        $this->assertTrue(app(AuditChain::class)->verify()['ok'], 'The first remaining record follows pruned ones.');
    }

    public function test_the_integrity_check_can_be_run_from_the_audit_page(): void
    {
        $admin = User::factory()->admin()->create();
        $this->fill(2);

        $this->actingAs($admin)->post('/admin/activity/verify')->assertRedirect()->assertSessionHas('status');
        $this->get('/admin/activity')->assertSee('audit-integrity', false);
    }

    public function test_system_entries_are_shown_in_the_viewers_language(): void
    {
        $admin = User::factory()->admin()->create();
        Activity::record('system', null, ['task' => 'currency', 'reason' => 'A record before this one was deleted.'], 'Exchange rates were not downloaded');

        $this->actingAs($admin)->withSession(['locale' => 'uz'])->get('/admin/activity?category=system')->assertOk()
            ->assertSee('Valyuta kurslari yuklanmadi')
            ->assertSee('Bundan oldingi yozuv o‘chirilgan.');
        $this->withSession(['locale' => 'ru'])->get('/admin/system')->assertOk()
            ->assertDontSee('Exchange rates were not downloaded');
    }
}
