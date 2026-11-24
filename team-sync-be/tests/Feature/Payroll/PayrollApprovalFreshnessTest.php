<?php

namespace Tests\Feature\Payroll;

use App\Exceptions\PayrollStateException;
use App\Models\Payroll;
use App\Models\PayrollActivityLog;
use App\Models\PayrollApproval;
use App\Models\PayrollApprovalPolicy;
use App\Models\PayrollDetail;
use App\Models\StaffMemberProfile;
use App\Models\User;
use App\Services\Payroll\PayrollApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PayrollApprovalFreshnessTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_applicable_policies_permit_simple_approval(): void
    {
        $payroll = $this->payroll();
        $this->policy(['min_amount' => 10000000]);

        $this->locked($payroll, function ($service, $payroll) {
            $service->approveForActor($payroll, null);
            $service->assertComplete($payroll);
            $this->assertCount(0, $service->currentApprovals($payroll));
        });
    }

    public function test_invalidation_preserves_decision_and_creates_fresh_revision_after_reopen(): void
    {
        $payroll = $this->payroll();
        $this->policy();
        $actor = $this->actor('director');

        $this->locked($payroll, function ($service, $payroll) use ($actor) {
            $service->approveForActor($payroll, $actor->id);
            $service->assertComplete($payroll);
            $old = $service->currentApprovals($payroll)->sole();
            $old->update(['notes' => 'Original decision']);
            $payroll->update(['status' => 'approved']);
            $service->invalidate($payroll, $actor->id);
            $payroll->update(['status' => 'pending']);

            $this->assertCount(0, $service->currentApprovals($payroll));
            $fresh = $service->synchronize($payroll)->sole();
            $this->assertSame(2, $fresh->revision);
            $this->assertSame('pending', $fresh->status);
            $this->assertNull($fresh->approver_id);
            $this->assertNotNull($old->fresh()->superseded_at);
            $this->assertSame('approved', $old->fresh()->status);
            $this->assertSame('Original decision', $old->fresh()->notes);
            $this->assertSame($actor->id, $old->fresh()->approver_id);
            $this->assertNotNull($old->fresh()->approved_at);
            $this->assertDatabaseHas('payroll_activity_logs', [
                'payroll_id' => $payroll->id,
                'actor_id' => $actor->id,
                'event_type' => 'approvals_invalidated',
            ]);
        });
    }

    public function test_threshold_crossing_archives_all_prior_steps_and_uses_detail_total(): void
    {
        $payroll = $this->payroll();
        $this->policy();
        $this->policy(['name' => 'Finance', 'required_role' => 'finance', 'min_amount' => 10000000, 'approval_order' => 2]);
        $actor = $this->actor('director');

        $this->locked($payroll, function ($service, $payroll) use ($actor) {
            $service->approveForActor($payroll, $actor->id);
            $old = $service->currentApprovals($payroll)->sole();
            $payroll->payrollDetails()->update(['final_salary' => 11000000]);
            $steps = $service->synchronize($payroll);

            $this->assertCount(2, $steps);
            $this->assertTrue($steps->every(fn ($step) => $step->revision === 2 && $step->status === 'pending'));
            $this->assertNotNull($old->fresh()->superseded_at);
            $this->expectException(PayrollStateException::class);
            $service->assertComplete($payroll);
        });
    }

    public function test_policy_role_order_and_threshold_edits_each_require_new_approvals(): void
    {
        $payroll = $this->payroll();
        $policy = $this->policy();

        $this->locked($payroll, function ($service, $payroll) use ($policy) {
            $previous = $service->synchronize($payroll)->sole();
            foreach ([['required_role' => 'finance'], ['approval_order' => 3], ['min_amount' => 100], ['max_amount' => 9000000]] as $edit) {
                $previous->update(['status' => 'approved']);
                $policy->update($edit);
                $current = $service->synchronize($payroll)->sole();
                $this->assertSame($previous->revision + 1, $current->revision);
                $this->assertSame('pending', $current->status);
                $this->assertNotNull($previous->fresh()->superseded_at);
                $previous = $current;
            }
            $previous->update(['status' => 'approved']);
            $payroll->payrollDetails()->update(['final_salary' => 6000000]);
            $this->assertSame($previous->revision + 1, $service->synchronize($payroll)->sole()->revision);
            $this->assertNotNull($previous->fresh()->superseded_at);
        });
    }

    public function test_rejected_step_cannot_be_bypassed_by_actor_approval_or_completion(): void
    {
        $payroll = $this->payroll();
        $this->policy();
        $actor = $this->actor('director');

        $this->locked($payroll, function ($service, $payroll) use ($actor) {
            $step = $service->synchronize($payroll)->sole();
            $step->update(['status' => 'rejected', 'approver_id' => $actor->id, 'notes' => 'Do not pay']);

            foreach (['approve', 'complete'] as $operation) {
                try {
                    if ($operation === 'approve') {
                        $service->approveForActor($payroll, $actor->id);
                    } else {
                        $service->assertComplete($payroll);
                    }
                    $this->fail('Rejected approval was bypassed.');
                } catch (PayrollStateException) {
                    $this->assertSame('rejected', $step->fresh()->status);
                }
            }
        });
    }

    public function test_null_unknown_and_wrong_role_actors_cannot_approve_pending_steps(): void
    {
        $payroll = $this->payroll();
        $this->policy();
        $wrongRole = $this->actor('finance');

        $this->locked($payroll, function ($service, $payroll) use ($wrongRole) {
            foreach ([null, $wrongRole->id, $wrongRole->id + 10000] as $actorId) {
                try {
                    $service->approveForActor($payroll, $actorId);
                    $this->fail('Actor without the required role approved a step.');
                } catch (PayrollStateException) {
                    $this->assertSame('pending', $service->currentApprovals($payroll)->sole()->status);
                }
            }
        });
    }

    public function test_partial_approval_blocks_completion_and_unchanged_sync_preserves_decisions(): void
    {
        $payroll = $this->payroll();
        $this->policy();
        $this->policy(['name' => 'Finance', 'required_role' => 'finance', 'approval_order' => 2]);
        $director = $this->actor('director');
        $finance = $this->actor('finance');

        $this->locked($payroll, function ($service, $payroll) use ($director, $finance) {
            $service->approveForActor($payroll, $director->id);
            $ids = $service->currentApprovals($payroll)->modelKeys();
            try {
                $service->assertComplete($payroll);
                $this->fail('Partial approval was accepted.');
            } catch (PayrollStateException) {
                $this->assertSame($ids, $service->synchronize($payroll)->modelKeys());
            }
            $service->approveForActor($payroll, $finance->id);
            $service->assertComplete($payroll);
            $this->assertSame($ids, $service->synchronize($payroll)->modelKeys());
            $this->assertSame(0, PayrollActivityLog::where('event_type', 'approvals_invalidated')->count());
        });
    }

    public function test_json_object_key_order_does_not_invalidate_unchanged_snapshot(): void
    {
        $payroll = $this->payroll();
        $this->policy();

        $this->locked($payroll, function ($service, $payroll) {
            $step = $service->synchronize($payroll)->sole();
            $step->update(['policy_snapshot' => array_reverse($step->policy_snapshot, true)]);
            $this->assertSame($step->id, $service->synchronize($payroll)->sole()->id);
        });
    }

    public function test_legacy_decisions_are_archived_without_being_trusted(): void
    {
        $payroll = $this->payroll();
        $policy = $this->policy();
        $legacy = PayrollApproval::create(['payroll_id' => $payroll->id, 'policy_id' => $policy->id, 'status' => 'approved']);

        $this->locked($payroll, function ($service, $payroll) use ($legacy) {
            $step = $service->synchronize($payroll)->sole();
            $this->assertSame(1, $step->revision);
            $this->assertSame('pending', $step->status);
            $this->assertNotNull($legacy->fresh()->superseded_at);
            $this->assertSame('approved', $legacy->fresh()->status);
        });
    }

    public function test_paid_history_is_not_rewritten_when_policy_changes(): void
    {
        $payroll = $this->payroll();
        $policy = $this->policy();
        $actor = $this->actor('director');

        $this->locked($payroll, function ($service, $payroll) use ($policy, $actor) {
            $service->approveForActor($payroll, $actor->id);
            $old = $service->currentApprovals($payroll)->sole();
            $payroll->update(['status' => 'paid']);
            $policy->update(['required_role' => 'finance']);
            $this->assertSame($old->id, $service->synchronize($payroll)->sole()->id);
            $this->assertNull($old->fresh()->superseded_at);
            $this->expectException(PayrollStateException::class);
            $service->invalidate($payroll);
        });
    }

    private function locked(Payroll $payroll, callable $callback): void
    {
        DB::transaction(function () use ($payroll, $callback) {
            $locked = Payroll::query()->whereKey($payroll->id)->lockForUpdate()->firstOrFail();
            $callback(app(PayrollApprovalService::class), $locked);
        });
    }

    private function policy(array $attributes = []): PayrollApprovalPolicy
    {
        return PayrollApprovalPolicy::create(array_merge([
            'name' => 'Director',
            'min_amount' => 0,
            'max_amount' => null,
            'required_role' => 'director',
            'approval_order' => 1,
            'is_active' => true,
        ], $attributes));
    }

    private function actor(string $role): User
    {
        $actor = User::factory()->create();
        $actor->assignRole(Role::findOrCreate($role, 'sanctum'));

        return $actor;
    }

    private function payroll(): Payroll
    {
        $staff = StaffMemberProfile::withoutSyncingToSearch(fn () => StaffMemberProfile::factory()->for(User::factory())->create());
        $payroll = Payroll::create(['salary_month' => '2026-11-01', 'status' => 'pending', 'correction_count' => 0]);
        PayrollDetail::create([
            'payroll_id' => $payroll->id,
            'staff_member_id' => $staff->id,
            'original_salary' => 5000000,
            'final_salary' => 5000000,
            'attended_days' => 22,
            'sick_days' => 0,
            'absent_days' => 0,
        ]);

        return $payroll;
    }
}
