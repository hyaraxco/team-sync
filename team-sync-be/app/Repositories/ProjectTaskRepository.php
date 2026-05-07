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

        if ($attachment->file_path && Storage::disk('public')->exists($attachment->file_path)) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $attachment->delete();

        return $attachment;
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
