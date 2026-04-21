<?php

namespace App\Repositories;

use App\DTOs\LeaveRequestDto;
use App\Interfaces\LeaveRequestRepositoryInterface;
use App\Models\AttendancePeriod;
use App\Models\JobInformation;
use App\Models\LeaveRequest;
use App\Models\PayrollAdjustment;
use App\Models\PayrollDetail;
use App\Models\Team;
use App\Models\TeamMember;
use App\Services\Attendance\AttendancePeriodService;
use App\Services\EmailService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LeaveRequestRepository implements LeaveRequestRepositoryInterface
{
    public function __construct(
        private EmailService $emailService,
        private AttendancePeriodService $attendancePeriodService
    ) {}

    public function getAll(
        ?string $search,
        ?int $limit,
        bool $execute
    ) {
        $query = LeaveRequest::with(['staffMember.user', 'approver.user'])
            ->where(function ($query) use ($search) {
                if ($search) {
                    $query->search($search);
                }
            })
            ->orderBy('created_at', 'desc');

        $manageableEmployeeIds = $this->getManageableEmployeeIdsForManager();
        if (is_array($manageableEmployeeIds)) {
            if (empty($manageableEmployeeIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('staff_member_id', $manageableEmployeeIds);
            }
        }

        if ($limit) {
            $query->take($limit);
        }

        if ($execute) {
            return $query->get();
        }

        return $query;
    }

    public function getAllPaginated(
        ?string $search,
        int $rowPerPage
    ) {
        $query = $this->getAll(
            $search,
            null,
            false
        );

        return $query->paginate($rowPerPage);
    }

    public function getById(
        string $id
    ) {
        $leaveRequest = LeaveRequest::with(['staffMember.user', 'approver.user'])
            ->findOrFail($id);

        $this->authorizeManagerScope($leaveRequest);

        return $leaveRequest;
    }

    public function getMyLeaveRequests()
    {
        return LeaveRequest::with(['staffMember.user', 'approver.user'])
            ->where('staff_member_id', Auth::user()->staffMemberProfile->getKey())
            ->whereDate('created_at', '>=', now()->subDays(6)->startOfDay())
            ->whereDate('created_at', '<=', now()->endOfDay())
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $startDate = Carbon::parse($data['start_date']);
            $endDate = Carbon::parse($data['end_date']);

            $cursor = $startDate->copy()->startOfDay();
            while ($cursor->lessThanOrEqualTo($endDate)) {
                if (! $this->attendancePeriodService->canSubmitCorrection($cursor)) {
                    throw new \Exception(sprintf(
                        'Leave request cannot be submitted for %s because the attendance period is no longer open.',
                        $cursor->toDateString()
                    ));
                }

                $cursor->addDay();
            }

            $data['total_days'] = $startDate->diffInDays($endDate) + 1;

            $leaveRequestDto = LeaveRequestDto::fromArray($data);
            $leaveRequest = LeaveRequest::create($leaveRequestDto->toArray());

            DB::afterCommit(function () use ($leaveRequest) {
                $this->emailService->sendLeaveRequestCreatedNotification($leaveRequest);
            });

            return $leaveRequest;
        });
    }

    public function approve(string $id)
    {
        return DB::transaction(function () use ($id) {
            $leaveRequest = $this->getById($id);

            $data = [
                'status' => 'approved',
                'approved_by' => Auth::user()->staffMemberProfile->getKey(),
            ];

            $leaveRequestDto = LeaveRequestDto::fromArrayForUpdate($data, $leaveRequest);
            $leaveRequest->update($leaveRequestDto->toArray());

            DB::afterCommit(function () use ($leaveRequest) {
                $this->emailService->sendLeaveRequestApprovedNotification($leaveRequest);
            });

            return $leaveRequest;
        });
    }

    public function reject(string $id)
    {
        return DB::transaction(function () use ($id) {
            $leaveRequest = $this->getById($id);

            $data = [
                'status' => 'rejected',
                'approved_by' => Auth::user()->staffMemberProfile->getKey(),
            ];

            $leaveRequestDto = LeaveRequestDto::fromArrayForUpdate($data, $leaveRequest);
            $leaveRequest->update($leaveRequestDto->toArray());

            DB::afterCommit(function () use ($leaveRequest) {
                $this->emailService->sendLeaveRequestRejectedNotification($leaveRequest);
            });

            return $leaveRequest;
        });
    }

    public function uploadProof(string $id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $leaveRequest = LeaveRequest::query()->findOrFail($id);

            $this->authorizeProofUpload($leaveRequest);

            $leaveType = (string) ($leaveRequest->leave_type->value ?? $leaveRequest->leave_type);
            if ($leaveType !== 'sick_leave') {
                throw new \Exception('Proof upload is only supported for sick leave requests.');
            }

            if ($leaveRequest->status === 'rejected') {
                throw new \Exception('Proof cannot be uploaded for rejected leave requests.');
            }

            /** @var UploadedFile $file */
            $file = $data['proof_file'];

            if ($leaveRequest->proof_file_path && Storage::disk('public')->exists($leaveRequest->proof_file_path)) {
                Storage::disk('public')->delete($leaveRequest->proof_file_path);
            }

            $storedPath = $file->store('leave-proofs', 'public');

            $leaveRequest->update([
                'proof_file_path' => $storedPath,
                'proof_file_name' => $file->getClientOriginalName(),
                'proof_mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
                'proof_size_kb' => (int) ceil(((int) $file->getSize()) / 1024),
                'proof_uploaded_at' => now(),
                'proof_review_status' => null,
                'proof_reviewed_by' => null,
                'proof_reviewed_at' => null,
                'proof_review_notes' => null,
            ]);

            $updatedLeaveRequest = $leaveRequest->fresh(['staffMember.user', 'approver.user', 'proofReviewedBy.user']);

            DB::afterCommit(function () use ($updatedLeaveRequest) {
                $this->emailService->sendLeaveProofUploadedNotification($updatedLeaveRequest, Auth::id());
            });

            return $updatedLeaveRequest;
        });
    }

    public function reviewProof(string $id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $leaveRequest = $this->getById($id);
            $reviewStatus = $data['proof_review_status'];
            $leaveType = (string) ($leaveRequest->leave_type->value ?? $leaveRequest->leave_type);

            if ($leaveType !== 'sick_leave') {
                throw new \Exception('Proof review is only supported for sick leave requests.');
            }

            if ($leaveRequest->status !== 'approved') {
                throw new \Exception('Sick proof can only be reviewed after leave request approval.');
            }

            if (($leaveRequest->proof_review_status ?? null) === 'approved' && $reviewStatus === 'rejected') {
                throw new \Exception('Approved sick proof cannot be changed to rejected.');
            }

            if ($reviewStatus === 'approved' && ! $this->hasProofAttachment($leaveRequest)) {
                throw new \Exception('Proof attachment is incomplete and cannot be approved.');
            }

            $leaveRequest->update([
                'proof_review_status' => $reviewStatus,
                'proof_reviewed_by' => Auth::user()?->staffMemberProfile?->getKey(),
                'proof_reviewed_at' => now(),
                'proof_review_notes' => $data['proof_review_notes'] ?? null,
            ]);

            if ($reviewStatus === 'approved') {
                $this->createPostLockAdjustmentsForApprovedSickProof($leaveRequest->fresh());
            }

            $updatedLeaveRequest = $leaveRequest->fresh(['staffMember.user', 'approver.user', 'proofReviewedBy.user']);

            DB::afterCommit(function () use ($updatedLeaveRequest) {
                $this->emailService->sendLeaveProofReviewedNotification($updatedLeaveRequest, Auth::id());
            });

            return $updatedLeaveRequest;
        });
    }

    private function getManageableEmployeeIdsForManager(): ?array
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (! $user || ! $user->hasRole('manager')) {
            return null;
        }

        $leadTeamIds = Team::where('team_lead_id', $user->id)
            ->pluck('id')
            ->toArray();

        if (empty($leadTeamIds)) {
            return [];
        }

        $fromTeamMembers = TeamMember::whereIn('team_id', $leadTeamIds)
            ->whereNull('left_at')
            ->pluck('staff_member_id')
            ->toArray();

        $fromJobInformation = JobInformation::whereIn('team_id', $leadTeamIds)
            ->pluck('staff_member_id')
            ->toArray();

        return array_values(array_unique(array_merge($fromTeamMembers, $fromJobInformation)));
    }

    private function authorizeManagerScope(LeaveRequest $leaveRequest): void
    {
        $manageableEmployeeIds = $this->getManageableEmployeeIdsForManager();
        if (! is_array($manageableEmployeeIds)) {
            return;
        }

        if (! in_array($leaveRequest->staff_member_id, $manageableEmployeeIds, true)) {
            throw new AuthorizationException('You can only access leave requests from your direct reports.');
        }
    }

    private function authorizeProofUpload(LeaveRequest $leaveRequest): void
    {
        $staffMemberProfileId = Auth::user()?->staffMemberProfile?->getKey();

        if (! $staffMemberProfileId || $leaveRequest->staff_member_id !== $staffMemberProfileId) {
            throw new AuthorizationException('You can only upload proof for your own leave requests.');
        }
    }

    private function hasProofAttachment(LeaveRequest $leaveRequest): bool
    {
        return filled($leaveRequest->proof_file_path)
            && filled($leaveRequest->proof_file_name)
            && filled($leaveRequest->proof_mime_type)
            && ! is_null($leaveRequest->proof_size_kb)
            && ! is_null($leaveRequest->proof_uploaded_at);
    }

    private function createPostLockAdjustmentsForApprovedSickProof(LeaveRequest $leaveRequest): void
    {
        DB::transaction(function () use ($leaveRequest) {
            $lockedSourcePeriodDayCounts = $this->collectLockedSourcePeriodDayCounts($leaveRequest);

            if (empty($lockedSourcePeriodDayCounts)) {
                return;
            }

            foreach ($lockedSourcePeriodDayCounts as $sourcePeriodId => $daysDelta) {
                // Lock source period row to serialize concurrent proof reviews for the same payroll window.
                $sourcePeriod = AttendancePeriod::query()
                    ->whereKey($sourcePeriodId)
                    ->lockForUpdate()
                    ->first();

                if (! $sourcePeriod) {
                    continue;
                }

                $targetPeriod = $this->resolveNextAdjustableTargetPeriod($sourcePeriod);

                $dailyRate = (float) PayrollDetail::query()
                    ->where('staff_member_id', $leaveRequest->staff_member_id)
                    ->whereHas('payroll', function ($query) use ($sourcePeriodId) {
                        $query->where('attendance_period_id', $sourcePeriodId);
                    })
                    ->orderByDesc('id')
                    ->value('daily_rate');

                $daysDeltaValue = round((float) $daysDelta, 2);
                $amountDelta = round($dailyRate * $daysDeltaValue, 2);

                $existingAdjustment = PayrollAdjustment::query()
                    ->where('staff_member_id', $leaveRequest->staff_member_id)
                    ->where('source_period_id', $sourcePeriodId)
                    ->where('target_period_id', $targetPeriod->id)
                    ->where('source_reference_type', 'leave_request')
                    ->where('source_reference_id', $leaveRequest->id)
                    ->where('adjustment_kind', PayrollAdjustment::KIND_ABSENCE_CORRECTION_CREDIT)
                    ->lockForUpdate()
                    ->first();

                if ($existingAdjustment && $existingAdjustment->status === PayrollAdjustment::STATUS_APPLIED) {
                    continue;
                }

                if ($existingAdjustment) {
                    $existingAdjustment->update([
                        'days_delta' => $daysDeltaValue,
                        'amount_delta' => $amountDelta,
                        'reason' => sprintf('Post-lock sick proof approved for leave request #%d.', $leaveRequest->id),
                        'status' => PayrollAdjustment::STATUS_APPROVED,
                    ]);

                    continue;
                }

                PayrollAdjustment::query()->create([
                    'staff_member_id' => $leaveRequest->staff_member_id,
                    'source_period_id' => $sourcePeriodId,
                    'target_period_id' => $targetPeriod->id,
                    'source_reference_type' => 'leave_request',
                    'source_reference_id' => $leaveRequest->id,
                    'adjustment_kind' => PayrollAdjustment::KIND_ABSENCE_CORRECTION_CREDIT,
                    'days_delta' => $daysDeltaValue,
                    'amount_delta' => $amountDelta,
                    'reason' => sprintf('Post-lock sick proof approved for leave request #%d.', $leaveRequest->id),
                    'status' => PayrollAdjustment::STATUS_APPROVED,
                ]);
            }
        });
    }

    private function resolveNextAdjustableTargetPeriod(AttendancePeriod $sourcePeriod): AttendancePeriod
    {
        $targetMonth = Carbon::parse((string) $sourcePeriod->start_date)
            ->startOfMonth()
            ->addMonthNoOverflow();

        // Skip locked periods so newly approved post-lock adjustments are never parked
        // on already finalized payroll windows.
        for ($attempt = 0; $attempt < 24; $attempt++) {
            $targetPeriod = $this->attendancePeriodService->ensurePeriodForMonth($targetMonth);

            if (! $targetPeriod->isLocked()) {
                return $targetPeriod;
            }

            $targetMonth = $targetMonth->copy()->addMonthNoOverflow();
        }

        return $this->attendancePeriodService->ensurePeriodForMonth($targetMonth);
    }

    private function collectLockedSourcePeriodDayCounts(LeaveRequest $leaveRequest): array
    {
        $start = Carbon::parse($leaveRequest->start_date)->startOfDay();
        $end = Carbon::parse($leaveRequest->end_date)->startOfDay();
        $periodDays = [];

        $cursor = $start->copy();
        while ($cursor->lessThanOrEqualTo($end)) {
            $period = $this->attendancePeriodService->periodForDate($cursor);

            if ($period && $period->isLocked()) {
                $periodDays[$period->id] = ($periodDays[$period->id] ?? 0) + 1;
            }

            $cursor->addDay();
        }

        return $periodDays;
    }
}
