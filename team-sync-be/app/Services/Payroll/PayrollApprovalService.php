<?php

namespace App\Services\Payroll;

use App\Enums\PayrollStatus;
use App\Exceptions\PayrollStateException;
use App\Models\Payroll;
use App\Models\PayrollApproval;
use App\Models\PayrollApprovalPolicy;
use App\Models\User;
use App\Services\PayrollActivityLogger;
use Illuminate\Database\Eloquent\Collection;

class PayrollApprovalService
{
    public function __construct(private readonly PayrollActivityLogger $activityLogger) {}

    // Callers must hold the payroll row lock in a transaction, including during detail edits.
    public function invalidate(Payroll $payroll, ?int $actorId = null): void
    {
        if ($payroll->status === PayrollStatus::PAID) {
            throw new PayrollStateException('Paid payroll approvals cannot be invalidated.');
        }

        $approvals = $this->currentApprovals($payroll);
        if ($approvals->isEmpty()) {
            return;
        }

        PayrollApproval::query()->whereKey($approvals->modelKeys())->update(['superseded_at' => now()]);
        $this->activityLogger->log(
            $payroll->id,
            'approvals_invalidated',
            'Payroll approvals invalidated',
            'Prior approval decisions were retained and require a fresh revision.',
            $actorId,
            ['approval_ids' => $approvals->modelKeys(), 'revisions' => $approvals->pluck('revision')->unique()->values()->all()]
        );
    }

    public function synchronize(Payroll $payroll): Collection
    {
        $approvals = $this->currentApprovals($payroll);
        if ($payroll->status === PayrollStatus::PAID) {
            return $approvals;
        }

        $amount = (float) $payroll->payrollDetails()->sum('final_salary');
        $policies = PayrollApprovalPolicy::getApplicablePolicies($amount);
        $snapshots = $policies->mapWithKeys(fn (PayrollApprovalPolicy $policy) => [
            $policy->id => [
                'required_role' => $policy->required_role,
                'approval_order' => $policy->approval_order,
                'min_amount' => $policy->min_amount,
                'max_amount' => $policy->max_amount,
                'total_amount' => number_format($amount, 2, '.', ''),
            ],
        ]);

        $matches = $approvals->count() === $policies->count()
            && $approvals->pluck('policy_id')->unique()->count() === $approvals->count()
            && $approvals->every(function (PayrollApproval $approval) use ($snapshots) {
                $actual = $approval->policy_snapshot;
                $expected = $snapshots->get($approval->policy_id);
                if ($approval->revision === null || $actual === null || $expected === null) {
                    return false;
                }
                // MySQL JSON storage may reorder object keys.
                ksort($actual);
                ksort($expected);

                return $actual === $expected;
            });

        if ($matches) {
            return $approvals;
        }

        $this->invalidate($payroll);
        $revision = ((int) PayrollApproval::query()->where('payroll_id', $payroll->id)->max('revision')) + 1;
        foreach ($policies as $policy) {
            PayrollApproval::create([
                'payroll_id' => $payroll->id,
                'policy_id' => $policy->id,
                'revision' => $revision,
                'policy_snapshot' => $snapshots->get($policy->id),
                'status' => PayrollApproval::STATUS_PENDING,
            ]);
        }

        return $this->currentApprovals($payroll);
    }

    public function approveForActor(Payroll $payroll, ?int $actorId): void
    {
        if ($payroll->status !== PayrollStatus::PENDING) {
            throw new PayrollStateException('Payroll must be pending to approve a step.');
        }

        $approvals = $this->synchronize($payroll);
        if ($approvals->isEmpty()) {
            return;
        }

        if ($approvals->contains('status', PayrollApproval::STATUS_REJECTED)) {
            throw new PayrollStateException('Payroll has a rejected approval step. Reopen it before requesting fresh approvals.');
        }

        $actor = $actorId === null ? null : User::find($actorId);
        $approval = $approvals->first(fn (PayrollApproval $approval) => $approval->status === PayrollApproval::STATUS_PENDING
            && $actor?->hasRole($approval->policy_snapshot['required_role']));

        if ($approval === null) {
            throw new PayrollStateException('No pending approval step found for your role.');
        }

        $approval->update([
            'status' => PayrollApproval::STATUS_APPROVED,
            'approver_id' => $actorId,
            'approved_at' => now(),
        ]);
        $this->activityLogger->log(
            $payroll->id,
            'approval_decision',
            'Approval decision submitted',
            'A current payroll approval step was approved.',
            $actorId,
            ['approval_id' => $approval->id, 'revision' => $approval->revision, 'decision' => PayrollApproval::STATUS_APPROVED]
        );
    }

    public function assertComplete(Payroll $payroll): void
    {
        if ($this->synchronize($payroll)->contains(fn (PayrollApproval $approval) => $approval->status !== PayrollApproval::STATUS_APPROVED)) {
            throw new PayrollStateException('Every current payroll approval step must be approved before approval or payment.');
        }
    }

    public function currentApprovals(Payroll $payroll): Collection
    {
        return PayrollApproval::query()
            ->with(['policy', 'approver'])
            ->where('payroll_id', $payroll->id)
            ->whereNull('superseded_at')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (PayrollApproval $approval) => $approval->policy_snapshot['approval_order'] ?? $approval->policy?->approval_order ?? 0)
            ->values();
    }
}
