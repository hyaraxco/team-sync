<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\AttendancePolicyMismatch;
use App\Models\LeaveRequest;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PayrollNotificationDelivery;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskAttachment;
use App\Models\ProjectTaskComment;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Notifications\AttendanceCheckedIn;
use App\Notifications\AttendanceCheckedOut;
use App\Notifications\AttendanceCorrectionApproved;
use App\Notifications\AttendanceCorrectionNeedsApproval;
use App\Notifications\AttendanceCorrectionRejected;
use App\Notifications\AttendanceMismatchRequiresReview;
use App\Notifications\AttendanceMismatchStatusChanged;
use App\Notifications\LeaveProofReviewed;
use App\Notifications\LeaveProofUploaded;
use App\Notifications\LeaveRequestApproved;
use App\Notifications\LeaveRequestCreated;
use App\Notifications\LeaveRequestNeedsApproval;
use App\Notifications\LeaveRequestRejected;
use App\Notifications\PayrollApproved;
use App\Notifications\PayrollDraftCreated;
use App\Notifications\PayrollPaid;
use App\Notifications\ProjectLifecycleUpdated;
use App\Notifications\ProjectTaskCollaborationUpdated;
use App\Notifications\ProjectTaskStatusChanged;
use App\Notifications\TaskAssigned;
use App\Notifications\TeamLeadChanged;
use App\Notifications\TeamMemberAdded;
use App\Notifications\TeamMemberRemoved;
use App\Notifications\TeamStatusChanged;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

class EmailService
{
    public function sendPayslipToEmployee(PayrollDetail $payrollDetail, string $pdfContent): void
    {
        $payrollDetail->loadMissing(['staffMember.user', 'payroll']);

        $user = $payrollDetail->staffMember?->user;

        if (! $user || ! $user->email) {
            throw new RuntimeException('Employee email address is unavailable.');
        }

        $salaryMonth = Carbon::parse($payrollDetail->payroll?->salary_month ?? now())->format('F Y');
        $filename = 'payslip-'.$payrollDetail->getKey().'.pdf';

        $body = sprintf(
            '<p>Hello %s,</p><p>Your payslip for <strong>%s</strong> is attached to this email.</p><p>Regards,<br>Team Sync HRIS</p>',
            e((string) ($user->name ?? 'Employee')),
            e($salaryMonth)
        );

        Mail::html($body, function ($message) use ($user, $salaryMonth, $filename, $pdfContent): void {
            $message
                ->to($user->email, $user->name)
                ->subject('Payslip '.$salaryMonth)
                ->attachData($pdfContent, $filename, [
                    'mime' => 'application/pdf',
                ]);
        });
    }

    public function sendTaskAssignedNotification(
        ProjectTask $task,
        ?string $assignedByName = null,
        bool $isReassignment = false
    ): void {
        $task->load(['assignee.user', 'project']);

        $user = $task->assignee?->user;

        if (! $user || ! $user->email) {
            return;
        }

        $isNonManagerEmployee = $user->hasRole('staff')
            && ! $user->hasRole('manager')
            && ! $user->hasRole('hr')
            && ! $user->hasRole('finance');

        if (! $isNonManagerEmployee) {
            return;
        }

        $user->notify(TaskAssigned::fromProjectTask($task, $assignedByName, $isReassignment));
    }

    public function sendProjectTaskStatusChangedNotification(
        ProjectTask $task,
        string $fromStatus,
        string $toStatus,
        ?string $reason = null,
        ?int $actorUserId = null,
        ?string $actorName = null,
    ): void {
        $task->loadMissing(['assignee.user', 'project.projectLeader.user']);

        $recipients = collect();

        if (in_array($toStatus, ['review', 'done'], true)) {
            $projectLeaderUser = $task->project?->projectLeader?->user;

            if ($projectLeaderUser instanceof User) {
                $recipients->push($projectLeaderUser);
            }
        }

        if ($toStatus === 'rejected') {
            $assigneeUser = $task->assignee?->user;

            if ($assigneeUser instanceof User) {
                $recipients->push($assigneeUser);
            }
        }

        $recipients = $recipients
            ->filter(fn ($user) => $user instanceof User)
            ->reject(function (User $user) use ($actorUserId): bool {
                return $actorUserId !== null && (int) $user->id === $actorUserId;
            })
            ->unique('id')
            ->values();

        foreach ($recipients as $recipient) {
            if (! $recipient->email) {
                continue;
            }

            $recipient->notify(ProjectTaskStatusChanged::fromProjectTask(
                $task,
                $fromStatus,
                $toStatus,
                $reason,
                $actorName,
            ));
        }
    }

    public function sendProjectTaskCommentAddedNotification(
        ProjectTask $task,
        ProjectTaskComment $comment,
        ?int $actorUserId = null,
        ?string $actorName = null,
    ): void {
        $task->loadMissing(['assignee.user', 'project.projectLeader.user', 'comments.staffMember.user']);
        $comment->loadMissing('staffMember.user');

        $commentSnippet = trim((string) $comment->comment);
        if ($commentSnippet !== '') {
            $commentSnippet = mb_substr($commentSnippet, 0, 120);
        } else {
            $commentSnippet = null;
        }

        $recipients = $this->resolveTaskStakeholderRecipients($task, $actorUserId);

        foreach ($recipients as $recipient) {
            if (! $recipient->email) {
                continue;
            }

            $recipient->notify(new ProjectTaskCollaborationUpdated(
                taskId: (int) $task->id,
                projectId: (int) $task->project_id,
                taskName: (string) $task->name,
                projectName: $task->project?->name,
                eventType: 'comment_added',
                actorName: $actorName,
                commentSnippet: $commentSnippet,
                fileName: null,
            ));
        }
    }

    public function sendProjectTaskAttachmentAddedNotification(
        ProjectTask $task,
        ProjectTaskAttachment $attachment,
        ?int $actorUserId = null,
        ?string $actorName = null,
    ): void {
        $task->loadMissing(['assignee.user', 'project.projectLeader.user', 'comments.staffMember.user']);

        $recipients = $this->resolveTaskStakeholderRecipients($task, $actorUserId);

        foreach ($recipients as $recipient) {
            if (! $recipient->email) {
                continue;
            }

            $recipient->notify(new ProjectTaskCollaborationUpdated(
                taskId: (int) $task->id,
                projectId: (int) $task->project_id,
                taskName: (string) $task->name,
                projectName: $task->project?->name,
                eventType: 'attachment_added',
                actorName: $actorName,
                commentSnippet: null,
                fileName: $attachment->file_name,
            ));
        }
    }

    public function sendProjectLifecycleNotification(
        Project $project,
        string $eventType,
        ?string $previousStatus = null,
        ?int $actorUserId = null,
        ?string $actorName = null,
    ): void {
        $project->loadMissing([
            'projectLeader.user',
            'teams.members.staffMember.user',
        ]);

        $projectLeaderUser = $project->projectLeader?->user;

        $teamMemberUsers = $project->teams
            ->flatMap(fn (Team $team) => $team->members)
            ->map(fn (TeamMember $member) => $member->staffMember?->user)
            ->filter(fn ($user) => $user instanceof User)
            ->values();

        $recipients = $teamMemberUsers;
        if ($projectLeaderUser instanceof User) {
            $recipients->push($projectLeaderUser);
        }

        $recipients = $recipients
            ->filter(fn ($user) => $user instanceof User)
            ->filter(function (User $user): bool {
                return $user->hasRole('staff') && ! $user->hasRole('finance');
            })
            ->reject(function (User $user) use ($actorUserId): bool {
                return $actorUserId !== null && (int) $user->id === $actorUserId;
            })
            ->unique('id')
            ->values();

        foreach ($recipients as $recipient) {
            if (! $recipient->email) {
                continue;
            }

            $recipient->notify(new ProjectLifecycleUpdated(
                projectId: (int) $project->id,
                projectName: (string) $project->name,
                eventType: $eventType,
                currentStatus: (string) $project->status,
                previousStatus: $previousStatus,
                actorName: $actorName,
            ));
        }
    }

    public function sendTeamMemberAddedNotification(
        Team $team,
        TeamMember $member,
        ?int $actorUserId = null,
        ?string $actorName = null,
    ): void {
        $team->loadMissing('leader');
        $member->loadMissing('staffMember.user');

        $memberUser = $member->staffMember?->user;
        $leaderUser = $team->leader;
        $memberName = (string) ($memberUser?->name ?: ($member->staffMember?->code ?: 'Employee'));
        $teamName = (string) $team->name;

        $recipients = collect([$memberUser, $leaderUser])
            ->filter(fn ($user) => $user instanceof User)
            ->reject(function (User $user) use ($actorUserId): bool {
                return $actorUserId !== null && (int) $user->id === $actorUserId;
            })
            ->unique('id')
            ->values();

        foreach ($recipients as $recipient) {
            if (! $recipient->email) {
                continue;
            }

            $recipient->notify(new TeamMemberAdded(
                teamId: (int) $team->id,
                teamName: $teamName,
                memberName: $memberName,
                actorName: $actorName,
                actionUrl: $this->resolveTeamNotificationActionUrl($recipient, (int) $team->id),
            ));
        }
    }

    public function sendTeamMemberRemovedNotification(
        Team $team,
        TeamMember $member,
        ?int $actorUserId = null,
        ?string $actorName = null,
    ): void {
        $team->loadMissing('leader');
        $member->loadMissing('staffMember.user');

        $memberUser = $member->staffMember?->user;
        $leaderUser = $team->leader;
        $memberName = (string) ($memberUser?->name ?: ($member->staffMember?->code ?: 'Employee'));
        $teamName = (string) $team->name;

        $recipients = collect([$memberUser, $leaderUser])
            ->filter(fn ($user) => $user instanceof User)
            ->reject(function (User $user) use ($actorUserId): bool {
                return $actorUserId !== null && (int) $user->id === $actorUserId;
            })
            ->unique('id')
            ->values();

        foreach ($recipients as $recipient) {
            if (! $recipient->email) {
                continue;
            }

            $recipient->notify(new TeamMemberRemoved(
                teamId: (int) $team->id,
                teamName: $teamName,
                memberName: $memberName,
                actorName: $actorName,
                actionUrl: $this->resolveTeamNotificationActionUrl($recipient, (int) $team->id),
            ));
        }
    }

    public function sendTeamLeadChangedNotification(
        Team $team,
        ?User $oldLeader,
        ?User $newLeader,
        ?int $actorUserId = null,
        ?string $actorName = null,
    ): void {
        $team->loadMissing('leader');

        $teamName = (string) $team->name;
        $oldLeaderName = $oldLeader?->name;
        $newLeaderName = $newLeader?->name;

        $recipients = collect([$oldLeader, $newLeader])
            ->filter(fn ($user) => $user instanceof User)
            ->reject(function (User $user) use ($actorUserId): bool {
                return $actorUserId !== null && (int) $user->id === $actorUserId;
            })
            ->unique('id')
            ->values();

        foreach ($recipients as $recipient) {
            if (! $recipient->email) {
                continue;
            }

            $recipient->notify(new TeamLeadChanged(
                teamId: (int) $team->id,
                teamName: $teamName,
                oldLeaderName: $oldLeaderName,
                newLeaderName: $newLeaderName,
                actorName: $actorName,
                actionUrl: $this->resolveTeamNotificationActionUrl($recipient, (int) $team->id),
            ));
        }
    }

    public function sendTeamStatusChangedNotification(
        Team $team,
        string $fromStatus,
        string $toStatus,
        ?int $actorUserId = null,
        ?string $actorName = null,
    ): void {
        $team->loadMissing(['leader', 'members.staffMember.user']);

        $leaderUser = $team->leader;
        $memberUsers = $team->members
            ->map(fn (TeamMember $member) => $member->staffMember?->user)
            ->filter(fn ($user) => $user instanceof User)
            ->values();

        $recipients = $memberUsers;
        if ($leaderUser instanceof User) {
            $recipients->push($leaderUser);
        }

        $recipients = $recipients
            ->filter(fn ($user) => $user instanceof User)
            ->reject(function (User $user) use ($actorUserId): bool {
                return $actorUserId !== null && (int) $user->id === $actorUserId;
            })
            ->unique('id')
            ->values();

        foreach ($recipients as $recipient) {
            if (! $recipient->email) {
                continue;
            }

            $recipient->notify(new TeamStatusChanged(
                teamId: (int) $team->id,
                teamName: (string) $team->name,
                fromStatus: $fromStatus,
                toStatus: $toStatus,
                actorName: $actorName,
                actionUrl: $this->resolveTeamNotificationActionUrl($recipient, (int) $team->id),
            ));
        }
    }

    public function sendAttendanceCheckedInNotification(Attendance $attendance): void
    {
        $attendance->loadMissing('staffMember.user');

        $user = $attendance->staffMember?->user;

        if (! $user || ! $user->email) {
            return;
        }

        $user->notify(AttendanceCheckedIn::fromAttendance($attendance));
    }

    public function sendAttendanceCheckedOutNotification(Attendance $attendance): void
    {
        $attendance->loadMissing('staffMember.user');

        $user = $attendance->staffMember?->user;

        if (! $user || ! $user->email) {
            return;
        }

        $user->notify(AttendanceCheckedOut::fromAttendance($attendance));
    }

    public function sendAttendanceMismatchAcknowledgedNotification(AttendancePolicyMismatch $mismatch): void
    {
        $mismatch->loadMissing(['staffMember.user', 'acknowledgedBy.user']);

        $this->sendAttendanceMismatchStatusChangedNotification(
            $mismatch,
            $mismatch->acknowledgedBy?->user?->name,
        );
    }

    public function sendAttendanceMismatchResolvedNotification(AttendancePolicyMismatch $mismatch): void
    {
        $mismatch->loadMissing(['staffMember.user', 'resolvedBy.user']);

        $this->sendAttendanceMismatchStatusChangedNotification(
            $mismatch,
            $mismatch->resolvedBy?->user?->name,
        );
    }

    public function sendAttendanceMismatchEscalatedNotification(AttendancePolicyMismatch $mismatch): void
    {
        $mismatch->loadMissing(['staffMember.user', 'staffMember.jobInformation']);

        $this->sendAttendanceMismatchStatusChangedNotification($mismatch, 'System');

        $employeeUserId = (int) ($mismatch->staffMember?->user?->id ?? 0);
        $hrRecipients = $this->resolveHrRecipients($employeeUserId > 0 ? $employeeUserId : null);

        foreach ($hrRecipients as $recipient) {
            if (! $recipient->email) {
                continue;
            }

            $recipient->notify(AttendanceMismatchRequiresReview::fromMismatch(
                $mismatch,
                AttendanceMismatchRequiresReview::EVENT_ESCALATED,
                'System',
            ));
        }
    }

    public function sendAttendanceMismatchCreatedNotification(AttendancePolicyMismatch $mismatch): void
    {
        $mismatch->loadMissing(['staffMember.user', 'staffMember.jobInformation']);

        $employeeUser = $mismatch->staffMember?->user;

        if ($employeeUser instanceof User && $employeeUser->email) {
            $employeeUser->notify(AttendanceMismatchStatusChanged::fromMismatch($mismatch, 'System'));
        }

        $managerRecipients = $this->resolveManagersForEmployee(
            (int) $mismatch->staff_member_id,
            $mismatch->staffMember?->jobInformation?->team_id,
        );

        $managerRecipients = $managerRecipients
            ->filter(fn ($user) => $user instanceof User)
            ->reject(function (User $user) use ($employeeUser): bool {
                return $employeeUser instanceof User && (int) $user->id === (int) $employeeUser->id;
            })
            ->unique('id')
            ->values();

        foreach ($managerRecipients as $recipient) {
            if (! $recipient->email) {
                continue;
            }

            $recipient->notify(AttendanceMismatchRequiresReview::fromMismatch(
                $mismatch,
                AttendanceMismatchRequiresReview::EVENT_CREATED,
                'System',
            ));
        }
    }

    private function sendAttendanceMismatchStatusChangedNotification(
        AttendancePolicyMismatch $mismatch,
        ?string $actorName = null,
    ): void {
        $user = $mismatch->staffMember?->user;

        if (! $user || ! $user->email) {
            return;
        }

        $user->notify(AttendanceMismatchStatusChanged::fromMismatch($mismatch, $actorName));
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveTaskStakeholderRecipients(ProjectTask $task, ?int $excludeUserId = null): Collection
    {
        $assigneeUser = $task->assignee?->user;
        $projectLeaderUser = $task->project?->projectLeader?->user;
        $commenterUsers = $task->comments
            ->map(fn (ProjectTaskComment $comment) => $comment->staffMember?->user)
            ->filter(fn ($user) => $user instanceof User)
            ->values();

        return collect([$assigneeUser, $projectLeaderUser])
            ->merge($commenterUsers)
            ->filter(fn ($user) => $user instanceof User)
            ->reject(function (User $user) use ($excludeUserId): bool {
                return $excludeUserId !== null && (int) $user->id === $excludeUserId;
            })
            ->unique('id')
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveManagersForEmployee(int $employeeId, mixed $jobTeamId = null): Collection
    {
        if ($employeeId <= 0) {
            return collect();
        }

        $teamIds = [];

        if (is_numeric($jobTeamId) && (int) $jobTeamId > 0) {
            $teamIds[] = (int) $jobTeamId;
        }

        $teamIds = array_values(array_unique(array_merge(
            $teamIds,
            TeamMember::query()
                ->where('staff_member_id', $employeeId)
                ->whereNull('left_at')
                ->pluck('team_id')
                ->map(fn ($teamId) => (int) $teamId)
                ->toArray()
        )));

        if (empty($teamIds)) {
            return collect();
        }

        $managerUserIds = Team::query()
            ->whereIn('id', $teamIds)
            ->whereNotNull('team_lead_id')
            ->pluck('team_lead_id')
            ->map(fn ($userId) => (int) $userId)
            ->toArray();

        if (empty($managerUserIds)) {
            return collect();
        }

        return User::query()->whereIn('id', $managerUserIds)->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveHrRecipients(?int $excludeUserId = null): Collection
    {
        return User::role('hr')
            ->get()
            ->filter(fn ($user) => $user instanceof User)
            ->reject(function (User $user) use ($excludeUserId): bool {
                return $excludeUserId !== null && (int) $user->id === $excludeUserId;
            })
            ->unique('id')
            ->values();
    }

    /**
     * Send leave request created notification
     */
    public function sendLeaveRequestCreatedNotification(LeaveRequest $leaveRequest): void
    {
        $leaveRequest->loadMissing(['staffMember.user', 'staffMember.jobInformation']);

        $requester = $leaveRequest->staffMember?->user;

        if ($requester && $requester->email) {
            $requester->notify(new LeaveRequestCreated($leaveRequest));
        }

        $reviewers = $this->resolveLeaveReviewRecipients($leaveRequest, $requester?->id);
        $requesterName = $requester?->name;

        foreach ($reviewers as $reviewer) {
            if (! $reviewer->email) {
                continue;
            }

            $reviewer->notify(LeaveRequestNeedsApproval::fromLeaveRequest($leaveRequest, $requesterName));
        }
    }

    /**
     * Send leave request approved notification
     */
    public function sendLeaveRequestApprovedNotification(LeaveRequest $leaveRequest): void
    {
        $user = $leaveRequest->staffMember?->user;

        if (! $user || ! $user->email) {
            return;
        }

        $user->notify(new LeaveRequestApproved($leaveRequest));
    }

    /**
     * Send leave request rejected notification
     */
    public function sendLeaveRequestRejectedNotification(LeaveRequest $leaveRequest): void
    {
        $user = $leaveRequest->staffMember?->user;

        if (! $user || ! $user->email) {
            return;
        }

        $user->notify(new LeaveRequestRejected($leaveRequest));
    }

    public function sendLeaveProofUploadedNotification(LeaveRequest $leaveRequest, ?int $actorUserId = null): void
    {
        $leaveRequest->loadMissing(['staffMember.user', 'staffMember.jobInformation']);

        $uploaderName = $leaveRequest->staffMember?->user?->name;
        $reviewers = $this->resolveLeaveReviewRecipients($leaveRequest, $actorUserId);

        foreach ($reviewers as $reviewer) {
            if (! $reviewer->email) {
                continue;
            }

            $reviewer->notify(LeaveProofUploaded::fromLeaveRequest($leaveRequest, $uploaderName));
        }
    }

    public function sendLeaveProofReviewedNotification(LeaveRequest $leaveRequest, ?int $actorUserId = null): void
    {
        $leaveRequest->loadMissing(['staffMember.user', 'proofReviewedBy.user']);

        $requester = $leaveRequest->staffMember?->user;

        if (! $requester || ! $requester->email) {
            return;
        }

        if ($actorUserId !== null && (int) $requester->id === $actorUserId) {
            return;
        }

        $reviewerName = $leaveRequest->proofReviewedBy?->user?->name;
        $reviewStatus = (string) ($leaveRequest->proof_review_status ?? 'approved');

        $requester->notify(LeaveProofReviewed::fromLeaveRequest($leaveRequest, $reviewerName, $reviewStatus));
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveLeaveReviewRecipients(LeaveRequest $leaveRequest, ?int $excludeUserId = null): Collection
    {
        $employeeId = (int) ($leaveRequest->staff_member_id ?? 0);

        if ($employeeId <= 0) {
            return collect();
        }

        $teamIds = [];
        $jobTeamId = $leaveRequest->staffMember?->jobInformation?->team_id;

        if (is_numeric($jobTeamId) && (int) $jobTeamId > 0) {
            $teamIds[] = (int) $jobTeamId;
        }

        $teamIds = array_values(array_unique(array_merge(
            $teamIds,
            TeamMember::query()
                ->where('staff_member_id', $employeeId)
                ->whereNull('left_at')
                ->pluck('team_id')
                ->map(fn ($teamId) => (int) $teamId)
                ->toArray()
        )));

        $managerUsers = collect();

        if (! empty($teamIds)) {
            $managerUserIds = Team::query()
                ->whereIn('id', $teamIds)
                ->whereNotNull('team_lead_id')
                ->pluck('team_lead_id')
                ->map(fn ($userId) => (int) $userId)
                ->toArray();

            if (! empty($managerUserIds)) {
                $managerUsers = User::query()
                    ->whereIn('id', $managerUserIds)
                    ->get();
            }
        }

        $hrUsers = User::role('hr')->get();

        return $managerUsers
            ->merge($hrUsers)
            ->filter(fn ($user) => $user instanceof User)
            ->reject(function (User $user) use ($excludeUserId): bool {
                return $excludeUserId !== null && (int) $user->id === $excludeUserId;
            })
            ->unique('id')
            ->values();
    }

    private function resolveTeamNotificationActionUrl(User $recipient, int $teamId): string
    {
        $isPureEmployee = $recipient->hasRole('staff')
            && ! $recipient->hasRole('manager')
            && ! $recipient->hasRole('hr')
            && ! $recipient->hasRole('finance');

        if ($isPureEmployee) {
            return '/admin/my-team';
        }

        return '/admin/teams/'.$teamId;
    }

    public function sendPayrollPaidNotifications(
        int $payrollId,
        string $triggerType = PayrollNotificationDelivery::TRIGGER_AUTO_PAID
    ): void {
        Payroll::findOrFail($payrollId);

        $payrollDetails = PayrollDetail::where('payroll_id', $payrollId)
            ->with('staffMember.user')
            ->get();

        foreach ($payrollDetails as $payrollDetail) {
            if (! $payrollDetail instanceof PayrollDetail) {
                continue;
            }

            $user = $payrollDetail->staffMember?->user;
            $recipientEmail = $user?->email;

            $basePayload = [
                'payroll_id' => $payrollId,
                'payroll_detail_id' => $payrollDetail->id,
                'staff_member_id' => $payrollDetail->staff_member_id,
                'recipient_email' => $recipientEmail,
                'channel' => 'mail',
                'trigger_type' => $triggerType,
            ];

            if (! $user || ! $recipientEmail) {
                PayrollNotificationDelivery::create([
                    ...$basePayload,
                    'delivery_status' => PayrollNotificationDelivery::STATUS_SKIPPED,
                    'failure_reason' => 'missing_recipient_email',
                ]);

                continue;
            }

            try {
                $user->notify(new PayrollPaid($payrollDetail));

                PayrollNotificationDelivery::create([
                    ...$basePayload,
                    'delivery_status' => PayrollNotificationDelivery::STATUS_SENT,
                    'sent_at' => now(),
                ]);
            } catch (Throwable $exception) {
                $failureMessage = trim($exception->getMessage());
                if ($failureMessage === '') {
                    $failureMessage = get_class($exception);
                }

                PayrollNotificationDelivery::create([
                    ...$basePayload,
                    'delivery_status' => PayrollNotificationDelivery::STATUS_FAILED,
                    'failure_reason' => substr($failureMessage, 0, 500),
                ]);
            }
        }
    }

    /**
     * Send attendance correction created notification to managers/HR
     */
    public function sendAttendanceCorrectionCreatedNotification(AttendanceCorrection $correction): void
    {
        $correction->loadMissing(['staffMember.user', 'attendance']);

        $requester = $correction->staffMember?->user;
        $requesterName = $requester?->name;
        $date = optional($correction->attendance)->date ?? now()->toDateString();

        $managers = $this->resolveManagersForEmployee($correction->staff_member_id, $requester?->id);
        $hrRecipients = $this->resolveHrRecipients($requester?->id);

        $recipients = $managers->merge($hrRecipients)->unique('id')->values();

        foreach ($recipients as $recipient) {
            if (! $recipient->email) {
                continue;
            }

            $recipient->notify(
                AttendanceCorrectionNeedsApproval::fromCorrection($correction, $date, $requesterName)
            );
        }
    }

    /**
     * Send attendance correction approved notification to employee
     */
    public function sendAttendanceCorrectionApprovedNotification(AttendanceCorrection $correction): void
    {
        $correction->loadMissing(['staffMember.user', 'attendance']);

        $user = $correction->staffMember?->user;

        if (! $user || ! $user->email) {
            return;
        }

        $user->notify(new AttendanceCorrectionApproved($correction));
    }

    /**
     * Send attendance correction rejected notification to employee
     */
    public function sendAttendanceCorrectionRejectedNotification(AttendanceCorrection $correction): void
    {
        $correction->loadMissing(['staffMember.user', 'attendance']);

        $user = $correction->staffMember?->user;

        if (! $user || ! $user->email) {
            return;
        }

        $user->notify(new AttendanceCorrectionRejected($correction));
    }

    /**
     * Send payroll draft created notification to HR and Finance users
     */
    public function sendPayrollDraftCreatedNotification(Payroll $payroll, ?string $actorName = null): void
    {
        $hrRecipients = $this->resolveHrRecipients(null);
        $financeRecipients = User::role('finance', 'sanctum')->get();

        $recipients = $hrRecipients->merge($financeRecipients)->unique('id')->values();

        foreach ($recipients as $recipient) {
            if (! $recipient->email) {
                continue;
            }

            $recipient->notify(new PayrollDraftCreated($payroll, $actorName));
        }
    }

    /**
     * Send payroll approved notification to HR and Finance users
     */
    public function sendPayrollApprovedNotification(Payroll $payroll, ?string $actorName = null): void
    {
        $hrRecipients = $this->resolveHrRecipients(null);
        $financeRecipients = User::role('finance', 'sanctum')->get();

        $recipients = $hrRecipients->merge($financeRecipients)->unique('id')->values();

        foreach ($recipients as $recipient) {
            if (! $recipient->email) {
                continue;
            }

            $recipient->notify(new PayrollApproved($payroll, $actorName));
        }
    }
}
