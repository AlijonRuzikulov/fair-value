<?php

namespace Tests\Feature\Admin;

use App\Enums\ValuationStatus;
use App\Models\Activity;
use App\Models\District;
use App\Models\RealEstate;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The approval of a valuation: submitted by the appraiser, approved or
 * returned by the director, reopened, cancelled, the act signed.
 */
class ApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $appraiser;

    private User $director;

    private RealEstate $flat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->appraiser = User::factory()->create();
        $this->director = User::factory()->admin()->create();
        $this->flat = RealEstate::factory()->residential()->create([
            'status' => ValuationStatus::InProgress, 'market_value' => 830_177_000, 'report_number' => null, 'appraiser_id' => $this->appraiser->id,
        ]);
    }

    private function step(User $user, string $action, ?string $comment = null)
    {
        return $this->actingAs($user)->post('/admin/real-estates/'.$this->flat->id.'/workflow', array_filter(['action' => $action, 'comment' => $comment]));
    }

    public function test_the_report_is_submitted_approved_and_the_act_signed(): void
    {
        $this->step($this->appraiser, 'submitted')->assertRedirect()->assertSessionHas('status');
        $this->assertSame(ValuationStatus::Review, $this->flat->fresh()->status);
        $this->step($this->appraiser, 'submitted')->assertSessionHas('error');
        $this->step($this->appraiser, 'approved')->assertForbidden();

        $this->step($this->director, 'approved')->assertRedirect();
        $flat = $this->flat->fresh();
        $this->assertSame(ValuationStatus::Completed, $flat->status);
        $this->assertNotNull($flat->report_number);
        $this->assertNotNull($flat->completed_at);

        $this->step($this->appraiser, 'accepted')->assertRedirect();
        $this->step($this->appraiser, 'accepted')->assertSessionHas('error');
        $this->assertSame(['submitted', 'approved', 'accepted'], $flat->steps()->pluck('action')->all());
        $this->assertSame(3, Activity::where('event', 'workflow')->count());

        $this->actingAs($this->appraiser)->withSession(['locale' => 'uz'])->get('/admin/real-estates/'.$this->flat->id)->assertOk()
            ->assertSee('Holati')->assertSee('Direktor hisobotni tasdiqladi')->assertSee('Sarflangan vaqt');
    }

    public function test_returning_and_reopening_need_a_reason_and_start_the_steps_again(): void
    {
        $this->step($this->appraiser, 'submitted');
        $this->step($this->director, 'returned')->assertSessionHasErrors('comment');
        $this->step($this->director, 'returned', 'Analoglar eskirgan')->assertRedirect();
        $this->assertSame(ValuationStatus::InProgress, $this->flat->fresh()->status);
        $this->assertNull($this->flat->fresh()->currentStep('submitted'), 'Returned: the submission no longer counts.');

        $this->step($this->appraiser, 'submitted');
        $this->step($this->director, 'approved');
        $number = $this->flat->fresh()->report_number;

        $this->step($this->appraiser, 'reopened', 'Qayta baholash')->assertForbidden();
        $this->step($this->director, 'reopened', 'Bank yangi sanani so‘radi')->assertRedirect();
        $flat = $this->flat->fresh();
        $this->assertSame(ValuationStatus::InProgress, $flat->status);
        $this->assertSame($number, $flat->report_number, 'The report keeps its number.');

        $this->step($this->appraiser, 'cancelled')->assertSessionHasErrors('comment');
        $this->step($this->appraiser, 'cancelled', 'Mijoz voz kechdi')->assertRedirect();
        $this->assertSame(ValuationStatus::Cancelled, $this->flat->fresh()->status);
    }

    public function test_a_report_without_a_value_is_not_submitted_and_operators_do_not_submit(): void
    {
        $this->flat->update(['market_value' => null]);
        $this->step($this->appraiser, 'submitted')->assertSessionHas('error');
        $this->step(User::factory()->operator()->create(), 'submitted')->assertForbidden();
        $this->assertSame(ValuationStatus::InProgress, $this->flat->fresh()->status);
    }

    public function test_with_approval_required_only_the_director_completes_a_report_on_the_form(): void
    {
        $this->seed(RegionSeeder::class);
        $district = District::first();
        $payload = [
            'kind' => 'residential', 'name' => 'Turar joy', 'region_id' => $district->region_id, 'district_id' => $district->id,
            'status' => 'completed', 'persons' => [],
        ];

        Setting::put(['require_approval' => '1']);
        $this->actingAs($this->appraiser)->put('/admin/real-estates/'.$this->flat->id, $payload)->assertSessionHasErrors('status');
        $this->actingAs($this->director)->put('/admin/real-estates/'.$this->flat->id, $payload)->assertSessionHasNoErrors();
        $this->assertSame(ValuationStatus::Completed, $this->flat->fresh()->status);
        // A completed report stays completed when the appraiser corrects it.
        $this->actingAs($this->appraiser)->put('/admin/real-estates/'.$this->flat->id, $payload)->assertSessionHasNoErrors();

        Setting::put(['require_approval' => '0']);
        $other = RealEstate::factory()->residential()->create(['status' => ValuationStatus::InProgress]);
        $this->actingAs($this->appraiser)->put('/admin/real-estates/'.$other->id, $payload)->assertSessionHasNoErrors();
        $this->assertSame(ValuationStatus::Completed, $other->fresh()->status);
    }
}
