<?php

namespace App\Repositories;

use App\DTOs\ProjectTaskDto;
use App\Enums\TaskStatus;
use App\Interfaces\ProjectTaskRepositoryInterface;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskAttachment;
use App\Models\ProjectTaskComment;
use App\Models\ProjectTaskStatusLog;
use App\Models\TeamMember;
use App\Services\EmailService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ProjectTaskRepository implements ProjectTaskRepositoryInterface
{
    public function __construct(
        private EmailService $emailService
    ) {}

    public function getAll(
        ?string $search,
        ?int $projectId,
        ?int $limit,
        bool $execute
    ): Builder|Collection {
        $query = ProjectTask::with(['project.teams', 'assignee.user'])
            ->where(function ($query) use ($search, $projectId) {
                if ($search) {
                    $query->search($search);
                }
                if ($projectId) {
                    $query->where('project_id', $projectId);
                }
            });

        $this->applyCurrentUserReadScope($query);

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
        ?int $projectId,
        int $rowPerPage
    ): LengthAwarePaginator {
        $query = $this->getAll(
            $search,
            $projectId,
            null,
            false
        );

        return $query->paginate($rowPerPage);
    }

    public function getById(
        string $id
    ): ProjectTask {
        $task = ProjectTask::with(['project.teams', 'assignee.user'])
            ->findOrFail($id);

        $this->assertCanReadTask($task);

        return $task;
    }

    public function getByProjectId(int $projectId): Collection
    {
        $query = ProjectTask::with(['project.teams', 'assignee.user'])
            ->where('project_id', $projectId);

        $this->applyCurrentUserReadScope($query);

        return $query->get();
    }

    public function create(array $data): ProjectTask
    {
        $this->authorizeTaskCreation($data);

        $taskDto = ProjectTaskDto::fromArray($data);
        $taskArray = $taskDto->toArray();

        $task = ProjectTask::create($taskArray);

        if ($task->assignee_id !== null) {
            $this->sendTaskAssignedNotification($task, false);
        }

        return $task;
    }

    public function update(string $id, array $data): ProjectTask
    {
        $task = $this->getById($id);
        $previousAssigneeId = $task->assignee_id !== null ? (int) $task->assignee_id : null;
        $hasAssigneeChange = $this->hasFieldChanged('assignee_id', $data, $task);

        $fromStatus = $this->normalizeStatusForWorkflow((string) $task->status);
        $toStatus = isset($data['status'])
            ? $this->normalizeStatusForWorkflow((string) $data['status'])
            : $fromStatus;
        $hasStatusChange = $fromStatus !== $toStatus;
        $isRejectedReassignment = $hasAssigneeChange && $fromStatus === TaskStatus::REJECTED->value;

        if ($isRejectedReassignment) {
            $toStatus = TaskStatus::TODO->value;
            $hasStatusChange = $fromStatus !== $toStatus;
        }

        $this->authorizeTaskUpdate($task, $data);

        $taskDto = ProjectTaskDto::fromArrayForUpdate($data, $task);
        $updatePayload = $taskDto->toArray();

        if ($hasStatusChange && $toStatus === TaskStatus::REJECTED->value) {
            $updatePayload['rejected_reason'] = trim((string) ($data['rejected_reason'] ?? ''));
            $updatePayload['rejected_by'] = Auth::user()?->staffMemberProfile?->id;
            $updatePayload['rejected_at'] = now();
        }

        if ($isRejectedReassignment) {
            $updatePayload['status'] = TaskStatus::TODO->value;
            $updatePayload['rejected_reason'] = null;
            $updatePayload['rejected_by'] = null;
            $updatePayload['rejected_at'] = null;
        }

        $task->update($updatePayload);

        $currentAssigneeId = $task->assignee_id !== null ? (int) $task->assignee_id : null;
        $statusChangeReason = $toStatus === TaskStatus::REJECTED->value
            ? ($data['rejected_reason'] ?? null)
            : null;

        if ($hasStatusChange) {
            $this->createStatusLog($task, $fromStatus, $toStatus, $statusChangeReason);
            $this->sendTaskStatusChangedNotification($task, $fromStatus, $toStatus, $statusChangeReason);
        }

        $assigneeChanged = $currentAssigneeId !== $previousAssigneeId;
        if ($assigneeChanged && $currentAssigneeId !== null) {
            $this->sendTaskAssignedNotification($task, $previousAssigneeId !== null);
        }

        return $task;
    }

    public function delete(string $id): ProjectTask
    {
        $task = $this->getById($id);

        $this->authorizeTaskDeletion($task);

        $task->delete();

        return $task;
    }

    public function getComments(string $taskId): Collection
    {
        $task = $this->getById($taskId);

        return $task->comments()
            ->with(['staffMember.user'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function createComment(string $taskId, array $data): ProjectTaskComment
    {
        $task = $this->getById($taskId);

        $user = Auth::user();
        if (! $user || ! $user->staffMemberProfile) {
            throw new AuthorizationException('Your account is not linked to an employee profile.');
        }

        $this->authorizeTaskCollaboration($task, 'create-comment');

        $comment = ProjectTaskComment::create([
            'project_task_id' => $task->id,
            'staff_member_id' => $user->staffMemberProfile->id,
            'comment' => $data['comment'],
        ])->load(['staffMember.user']);

        $this->emailService->sendProjectTaskCommentAddedNotification(
            $task,
            $comment,
            Auth::id(),
            Auth::user()?->name,
        );

        return $comment;
    }

    public function updateComment(string $taskId, string $commentId, array $data): ProjectTaskComment
    {
        $task = $this->getById($taskId);
        $comment = ProjectTaskComment::where('project_task_id', $task->id)
            ->findOrFail($commentId);

        $this->authorizeCommentMutation($task, $comment);

        $comment->update([
            'comment' => $data['comment'],
        ]);

        return $comment->load(['staffMember.user']);
    }

    public function deleteComment(string $taskId, string $commentId): ProjectTaskComment
    {
        $task = $this->getById($taskId);
        $comment = ProjectTaskComment::where('project_task_id', $task->id)
            ->findOrFail($commentId);

        $this->authorizeCommentMutation($task, $comment);

        $comment->delete();

        return $comment;
    }

    public function getAttachments(string $taskId): Collection
    {
        $task = $this->getById($taskId);

        return $task->attachments()
            ->with(['staffMember.user'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function getStatusLogs(string $taskId): Collection
    {
        $task = $this->getById($taskId);

        if (! Schema::hasTable('project_task_status_logs')) {
            return new Collection;
        }

        return $task->statusLogs()
            ->with(['changedBy.user'])
            ->orderBy('changed_at', 'desc')
            ->get();
    }

    public function createAttachment(string $taskId, array $data): ProjectTaskAttachment
    {
        $task = $this->getById($taskId);

        $user = Auth::user();
        if (! $user || ! $user->staffMemberProfile) {
            throw new AuthorizationException('Your account is not linked to an employee profile.');
        }

        $this->authorizeTaskCollaboration($task, 'create-attachment');

        $file = $data['file'];
        $storedPath = $file->store('task-attachments', 'public');

        $attachment = ProjectTaskAttachment::create([
            'project_task_id' => $task->id,
            'staff_member_id' => $user->staffMemberProfile->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $storedPath,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getClientMimeType(),
        ])->load(['staffMember.user']);

        $this->emailService->sendProjectTaskAttachmentAddedNotification(
            $task,
            $attachment,
            Auth::id(),
            Auth::user()?->name,
        );

        return $attachment;
    }

    public function deleteAttachment(string $taskId, string $attachmentId): ProjectTaskAttachment
    {
        $task = $this->getById($taskId);
        $attachment = ProjectTaskAttachment::where('project_task_id', $task->id)
            ->findOrFail($attachmentId);

        $this->authorizeAttachmentMutation($task, $attachment);

        if ($attachment->file_path && Storage::disk('public')->exists($attachment->file_path)) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $attachment->delete();

        return $attachment;
    }

    private function authorizeTaskUpdate(ProjectTask $task, array $data): void
    {
        $user = Auth::user();
        if (! $user) {
            throw new AuthorizationException('Unauthorized.');
        }

        $task->loadMissing('project');

        $staffMemberProfileId = $user->staffMemberProfile?->id;
        $isManager = $user->hasRole('manager');
        $isHr = $user->hasRole('hr');
        $isEmployee = $user->hasRole('staff');
        $isReviewerRole = $isManager || $isHr;
        $isPureEmployee = $isEmployee && ! $isReviewerRole;
        $isProjectLeader = $staffMemberProfileId !== null && $task->project?->project_leader_id === $staffMemberProfileId;
        $isAssignee = $staffMemberProfileId !== null && $task->assignee_id === $staffMemberProfileId;

        if ($isPureEmployee && ! $isAssignee && ! $isProjectLeader) {
            throw new AuthorizationException('You can only update your own assigned tasks.');
        }

        $hasAssigneeChange = $this->hasFieldChanged('assignee_id', $data, $task);
        $hasDueDateChange = $this->hasFieldChanged('due_date', $data, $task);

        $currentStatus = $this->normalizeStatusForWorkflow((string) $task->status);
        $isLockedForReviewerFieldEdit = in_array($currentStatus, [
            TaskStatus::REVIEW->value,
            TaskStatus::DONE->value,
        ], true);

        if (($hasAssigneeChange || $hasDueDateChange) && ($isReviewerRole || $isProjectLeader) && $isLockedForReviewerFieldEdit) {
            throw new AuthorizationException('Assignee and due date can only be changed before review or after task is rejected.');
        }

        if ($hasAssigneeChange && ! ($isReviewerRole || $isProjectLeader)) {
            throw new AuthorizationException('Only manager/HR/project leader can reassign task assignee.');
        }

        $hasStatusChange = $this->hasFieldChanged('status', $data, $task);
        if ($hasStatusChange) {
            $fromStatus = $this->normalizeStatusForWorkflow((string) $task->status);
            $toStatus = $this->normalizeStatusForWorkflow((string) $data['status']);
            $this->validateStatusTransition($fromStatus, $toStatus, $isPureEmployee, $isReviewerRole || $isProjectLeader);

            $isReviewerTransitionToRejected =
                ($isReviewerRole || $isProjectLeader) &&
                in_array($fromStatus, [TaskStatus::REVIEW->value, TaskStatus::DONE->value], true) &&
                $toStatus === TaskStatus::REJECTED->value;

            if ($isReviewerTransitionToRejected) {
                $reason = trim((string) ($data['rejected_reason'] ?? ''));
                if ($reason === '') {
                    throw new AuthorizationException('Rejected reason is required for this transition.');
                }
            }
        }

        if ($isPureEmployee) {
            $blockedFields = ['project_id', 'name', 'description', 'priority', 'due_date', 'rejected_reason'];

            foreach ($blockedFields as $field) {
                if ($this->hasFieldChanged($field, $data, $task)) {
                    throw new AuthorizationException('You are not allowed to modify '.$field.'.');
                }
            }
        }
    }

    private function authorizeTaskCreation(array $data): void
    {
        $user = Auth::user();
        if (! $user || ! $user->staffMemberProfile) {
            throw new AuthorizationException('Your account is not linked to an employee profile.');
        }

        $isManager = $user->hasRole('manager');
        $isHr = $user->hasRole('hr');
        $isReviewerRole = $isManager || $isHr;
        $isPureEmployee = $user->hasRole('staff') && ! $isReviewerRole;

        $projectId = $data['project_id'] ?? null;
        if (! $projectId) {
            throw new AuthorizationException('Project ID is required.');
        }

        // Staff must be a member of the project to create tasks
        if ($isPureEmployee) {
            $employee = $user->staffMemberProfile;
            $project = Project::with('teams')->find($projectId);

            if (! $project) {
                throw new AuthorizationException('Project not found.');
            }

            $isLeader = $project->project_leader_id === $employee->id;

            $jobInfoTeamId = $employee->jobInformation->team_id ?? null;
            $teamMemberIds = TeamMember::where('staff_member_id', $employee->id)
                ->whereNull('left_at')
                ->pluck('team_id')
                ->toArray();
            $teamIds = array_unique(array_filter(array_merge(
                $jobInfoTeamId ? [$jobInfoTeamId] : [],
                $teamMemberIds
            )));

            $projectTeamIds = $project->teams->pluck('id')->toArray();
            $isTeamAssigned = ! empty(array_intersect($projectTeamIds, $teamIds));

            if (! $isLeader && ! $isTeamAssigned) {
                throw new AuthorizationException('You can only create tasks in projects you are a member of.');
            }

            // Staff cannot assign tasks to others
            $assigneeId = $data['assignee_id'] ?? null;
            if ($assigneeId !== null && (int) $assigneeId !== $employee->id) {
                throw new AuthorizationException('You can only assign tasks to yourself.');
            }

            // Staff can only create tasks with initial status 'todo'
            $status = $data['status'] ?? 'todo';
            if ($status !== 'todo') {
                throw new AuthorizationException('Tasks must be created with status "todo".');
            }
        }
    }

    private function authorizeTaskDeletion(ProjectTask $task): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasRole('manager')) {
            throw new AuthorizationException('Only manager can delete tasks.');
        }
    }

    private function validateStatusTransition(string $from, string $to, bool $isEmployee, bool $isReviewer): void
    {
        if ($isEmployee) {
            $employeeTransitions = [
                TaskStatus::TODO->value => [TaskStatus::IN_PROGRESS->value],
                TaskStatus::IN_PROGRESS->value => [TaskStatus::REVIEW->value],
                TaskStatus::REJECTED->value => [TaskStatus::IN_PROGRESS->value],
            ];

            $allowed = $employeeTransitions[$from] ?? [];
            if (! in_array($to, $allowed, true)) {
                throw new AuthorizationException("Invalid status transition from {$from} to {$to}.");
            }

            return;
        }

        if ($isReviewer) {
            $reviewerTransitions = [
                TaskStatus::REVIEW->value => [TaskStatus::DONE->value, TaskStatus::REJECTED->value],
                TaskStatus::DONE->value => [TaskStatus::REJECTED->value],
            ];

            if ($from === $to) {
                return;
            }

            $allowed = $reviewerTransitions[$from] ?? [];
            if (! in_array($to, $allowed, true)) {
                throw new AuthorizationException("Invalid reviewer status transition from {$from} to {$to}.");
            }
        }
    }

    private function hasFieldChanged(string $field, array $data, ProjectTask $task): bool
    {
        if (! array_key_exists($field, $data)) {
            return false;
        }

        $incoming = $this->normalizeFieldValue($field, $data[$field]);
        $current = $this->normalizeFieldValue($field, $task->{$field});

        return $incoming !== $current;
    }

    private function normalizeFieldValue(string $field, mixed $value): mixed
    {
        if ($value === '') {
            return null;
        }

        if ($field === 'due_date') {
            return $value ? (string) date('Y-m-d', strtotime((string) $value)) : null;
        }

        return $value;
    }

    private function applyCurrentUserReadScope(Builder $query): void
    {
        $user = Auth::user();
        $isReviewerRole = $user && ($user->hasRole('manager') || $user->hasRole('hr'));
        $isPureEmployee = $user && $user->hasRole('staff') && ! $isReviewerRole;

        if (! $isPureEmployee) {
            return;
        }

        $employee = $user->staffMemberProfile;
        if (! $employee) {
            $query->whereRaw('1 = 0');

            return;
        }

        $jobInfoTeamId = $employee->jobInformation->team_id ?? null;
        $teamMemberIds = TeamMember::where('staff_member_id', $employee->id)
            ->whereNull('left_at')
            ->pluck('team_id')
            ->toArray();
        $teamIds = array_unique(array_filter(array_merge(
            $jobInfoTeamId ? [$jobInfoTeamId] : [],
            $teamMemberIds
        )));

        $query->where(function (Builder $taskQuery) use ($employee, $teamIds) {
            $taskQuery->where('assignee_id', $employee->id)
                ->orWhereHas('project', function (Builder $projectQuery) use ($employee, $teamIds) {
                    $projectQuery->where('project_leader_id', $employee->id);

                    if (! empty($teamIds)) {
                        $projectQuery->orWhereHas('teams', function (Builder $teamQuery) use ($teamIds) {
                            $teamQuery->whereIn('teams.id', $teamIds);
                        });
                    }
                });
        });
    }

    private function assertCanReadTask(ProjectTask $task): void
    {
        $user = Auth::user();
        $isReviewerRole = $user && ($user->hasRole('manager') || $user->hasRole('hr'));
        $isPureEmployee = $user && $user->hasRole('staff') && ! $isReviewerRole;

        if (! $isPureEmployee) {
            return;
        }

        $employee = $user->staffMemberProfile;
        if (! $employee) {
            throw new AuthorizationException('Forbidden.');
        }

        $task->loadMissing('project.teams');

        $jobInfoTeamId = $employee->jobInformation->team_id ?? null;
        $teamMemberIds = TeamMember::where('staff_member_id', $employee->id)
            ->whereNull('left_at')
            ->pluck('team_id')
            ->toArray();
        $teamIds = array_unique(array_filter(array_merge(
            $jobInfoTeamId ? [$jobInfoTeamId] : [],
            $teamMemberIds
        )));

        $projectTeamIds = $task->project?->teams?->pluck('id')->toArray() ?? [];
        $isAssignee = $task->assignee_id === $employee->id;
        $isLeader = $task->project?->project_leader_id === $employee->id;
        $isTeamAssigned = ! empty(array_intersect($projectTeamIds, $teamIds));

        if (! $isAssignee && ! $isLeader && ! $isTeamAssigned) {
            throw new AuthorizationException('Forbidden.');
        }
    }

    private function normalizeStatusForWorkflow(string $status): string
    {
        if ($status === 'pending') {
            return TaskStatus::TODO->value;
        }

        return $status;
    }

    private function authorizeTaskCollaboration(ProjectTask $task, string $action): void
    {
        $user = Auth::user();
        if (! $user || ! $user->staffMemberProfile) {
            throw new AuthorizationException('Unauthorized.');
        }

        $staffMemberProfileId = $user->staffMemberProfile->id;
        $isManager = $user->hasRole('manager');
        $isHr = $user->hasRole('hr');
        $isReviewerRole = $isManager || $isHr;
        $isPureEmployee = $user->hasRole('staff') && ! $isReviewerRole;
        $isProjectLeader = $task->project?->project_leader_id === $staffMemberProfileId;
        $isAssignee = $task->assignee_id === $staffMemberProfileId;

        if (! $isReviewerRole && ! $isProjectLeader && ! $isAssignee) {
            throw new AuthorizationException('Forbidden.');
        }

        if ($isPureEmployee) {
            if (! $isAssignee) {
                throw new AuthorizationException('You can only collaborate on your own assigned tasks.');
            }

            $lockedStatuses = [
                TaskStatus::REVIEW->value,
                TaskStatus::DONE->value,
                TaskStatus::CANCELLED->value,
            ];

            $currentStatus = $this->normalizeStatusForWorkflow((string) $task->status);
            if (in_array($currentStatus, $lockedStatuses, true)) {
                throw new AuthorizationException('Task is locked for this action in current status.');
            }
        }
    }

    private function authorizeCommentMutation(ProjectTask $task, ProjectTaskComment $comment): void
    {
        $user = Auth::user();
        if (! $user || ! $user->staffMemberProfile) {
            throw new AuthorizationException('Unauthorized.');
        }

        $staffMemberProfileId = $user->staffMemberProfile->id;
        $isOwner = $comment->staff_member_id === $staffMemberProfileId;

        if (! $isOwner) {
            throw new AuthorizationException('You are not allowed to modify this comment.');
        }

        if ($user->hasRole('staff') && ! ($user->hasRole('manager') || $user->hasRole('hr')) && $isOwner) {
            $this->authorizeTaskCollaboration($task, 'update-comment');
        }
    }

    private function authorizeAttachmentMutation(ProjectTask $task, ProjectTaskAttachment $attachment): void
    {
        $user = Auth::user();
        if (! $user || ! $user->staffMemberProfile) {
            throw new AuthorizationException('Unauthorized.');
        }

        $staffMemberProfileId = $user->staffMemberProfile->id;
        $isOwner = $attachment->staff_member_id === $staffMemberProfileId;

        if (! $isOwner) {
            throw new AuthorizationException('You are not allowed to modify this attachment.');
        }

        if ($user->hasRole('staff') && ! ($user->hasRole('manager') || $user->hasRole('hr')) && $isOwner) {
            $this->authorizeTaskCollaboration($task, 'delete-attachment');
        }
    }

    private function createStatusLog(ProjectTask $task, string $fromStatus, string $toStatus, ?string $reason = null): void
    {
        if (! Schema::hasTable('project_task_status_logs')) {
            return;
        }

        ProjectTaskStatusLog::create([
            'project_task_id' => $task->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => Auth::user()?->staffMemberProfile?->id,
            'reason' => $reason ? trim((string) $reason) : null,
            'changed_at' => now(),
        ]);
    }

    private function sendTaskAssignedNotification(ProjectTask $task, bool $isReassignment): void
    {
        $task->load(['assignee.user', 'project']);

        $this->emailService->sendTaskAssignedNotification(
            $task,
            Auth::user()?->name,
            $isReassignment,
        );
    }

    private function sendTaskStatusChangedNotification(
        ProjectTask $task,
        string $fromStatus,
        string $toStatus,
        ?string $reason = null,
    ): void {
        $this->emailService->sendProjectTaskStatusChangedNotification(
            $task,
            $fromStatus,
            $toStatus,
            $reason,
            Auth::id(),
            Auth::user()?->name,
        );
    }
}
