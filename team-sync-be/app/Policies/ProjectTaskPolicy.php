<?php

namespace App\Policies;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use App\Services\ProjectMembershipService;
use Illuminate\Auth\Access\Response;

class ProjectTaskPolicy
{
    public function __construct(
        private readonly ProjectMembershipService $membershipService
    ) {}

    /**
     * Determine if the user can view any tasks (index/list).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['task-list', 'task-create', 'task-edit', 'task-delete']);
    }

    /**
     * Determine if the user can view a specific task.
     * Staff can only view tasks in their assigned projects.
     */
    public function view(User $user, ProjectTask $task): Response
    {
        if ($this->isReviewerRole($user)) {
            return Response::allow();
        }

        $task->loadMissing('project.teams');

        if (! $this->membershipService->isMember($user, $task->project)) {
            return Response::deny('You can only view tasks in projects you are a member of.');
        }

        return Response::allow();
    }

    /**
     * Determine if the user can create a task.
     *
     * PL/Manager: can create tasks with any field, assign to any project member.
     * Staff: can only create tasks in own project, self-assign, status=todo.
     */
    public function create(User $user, array $data = []): Response
    {
        $profile = $user->staffMemberProfile;
        if (! $profile) {
            return Response::deny('Your account is not linked to an employee profile.');
        }

        $projectId = $data['project_id'] ?? null;
        if (! $projectId) {
            return Response::deny('Project ID is required.');
        }

        $project = Project::with('teams')->find($projectId);
        if (! $project) {
            return Response::deny('Project not found.');
        }

        // Assignee must be a project member (applies to ALL roles)
        $assigneeId = $data['assignee_id'] ?? null;
        if ($assigneeId !== null) {
            if (! $this->membershipService->isMemberById((int) $assigneeId, $project)) {
                return Response::deny('Assignee must be a member of the project.');
            }
        }

        // Manager/HR can create freely (assignee already validated above)
        if ($this->isReviewerRole($user)) {
            return Response::allow();
        }

        // PL can create freely in their project
        if ($project->project_leader_id === $profile->id) {
            return Response::allow();
        }

        // Pure staff checks
        if (! $this->membershipService->isMember($user, $project)) {
            return Response::deny('You can only create tasks in projects you are a member of.');
        }

        // Staff cannot assign to others
        if ($assigneeId !== null && (int) $assigneeId !== $profile->id) {
            return Response::deny('You can only assign tasks to yourself.');
        }

        // Staff can only create with status 'todo'
        $status = $data['status'] ?? 'todo';
        if ($status !== 'todo') {
            return Response::deny('Tasks must be created with status "todo".');
        }

        return Response::allow();
    }

    /**
     * Determine if the user can update a task.
     *
     * Flow rules:
     * - PL/Manager: can edit task fields ONLY when status=todo
     * - PL/Manager: LOCKED during in_progress (staff is working)
     * - PL/Manager: can review→done or review→rejected
     * - Staff: can only change status (drag/drop transitions)
     * - Staff: todo→in_progress, in_progress→review, rejected→in_progress
     */
    public function update(User $user, ProjectTask $task, array $data = []): Response
    {
        $task->loadMissing('project');

        $profile = $user->staffMemberProfile;
        if (! $profile) {
            return Response::deny('Your account is not linked to an employee profile.');
        }

        $isReviewer = $this->isReviewerRole($user);
        $isProjectLeader = $task->project?->project_leader_id === $profile->id;
        $isPrivileged = $isReviewer || $isProjectLeader;
        $isAssignee = $task->assignee_id === $profile->id;

        $currentStatus = strtolower(trim((string) $task->status));

        // ─── Basic access check ─────────────────────────────────────
        if (! $isPrivileged && ! $isAssignee) {
            return Response::deny('You can only update your own assigned tasks.');
        }

        // If no data provided, just check basic access
        if (empty($data)) {
            return Response::allow();
        }

        // ─── Assignee must be project member (applies to ALL roles) ──
        if ($this->hasFieldChanged('assignee_id', $data, $task)) {
            $newAssigneeId = (int) $data['assignee_id'];
            if (! $this->membershipService->isMemberById($newAssigneeId, $task->project)) {
                return Response::deny('Assignee must be a member of the project.');
            }
        }

        // ─── Status transition check ────────────────────────────────
        $hasStatusChange = $this->hasFieldChanged('status', $data, $task);
        if ($hasStatusChange) {
            $toStatus = strtolower(trim((string) $data['status']));
            $transitionResponse = $this->transitionStatus($user, $task, $currentStatus, $toStatus);
            if ($transitionResponse->denied()) {
                return $transitionResponse;
            }
        }

        // ─── Privileged user (PL/Manager) field edit rules ──────────
        if ($isPrivileged) {
            // Special case: reassign is allowed when status=rejected
            // (reassign to different staff → resets to todo in repository)
            $isReassignOnRejected = $this->hasFieldChanged('assignee_id', $data, $task)
                && $currentStatus === TaskStatus::REJECTED->value;

            $editableFields = ['name', 'description', 'priority', 'due_date', 'assignee_id', 'project_id'];
            $hasFieldEdit = false;
            foreach ($editableFields as $field) {
                if ($this->hasFieldChanged($field, $data, $task)) {
                    // Skip assignee_id check if it's a reassign-on-rejected
                    if ($field === 'assignee_id' && $isReassignOnRejected) {
                        continue;
                    }
                    $hasFieldEdit = true;
                    break;
                }
            }

            if ($hasFieldEdit) {
                // PL/Manager can only edit task fields when status=todo
                if ($currentStatus !== TaskStatus::TODO->value) {
                    return Response::deny('Task fields can only be edited when status is "todo".');
                }
            }

            // Rejected reason required when rejecting
            if ($hasStatusChange) {
                $toStatus = strtolower(trim((string) $data['status']));
                if ($toStatus === TaskStatus::REJECTED->value) {
                    $reason = trim((string) ($data['rejected_reason'] ?? ''));
                    if ($reason === '') {
                        return Response::deny('Rejected reason is required when rejecting a task.');
                    }
                }
            }

            return Response::allow();
        }

        // ─── Staff field edit rules ─────────────────────────────────
        // Staff can ONLY change status (via drag/drop or detail modal button)
        $staffBlockedFields = ['project_id', 'name', 'description', 'priority', 'due_date', 'assignee_id', 'rejected_reason'];
        foreach ($staffBlockedFields as $field) {
            if ($this->hasFieldChanged($field, $data, $task)) {
                return Response::deny('You are not allowed to modify '.$field.'.');
            }
        }

        return Response::allow();
    }

    /**
     * Determine if the user can delete a task.
     * Only PL/Manager can delete, and only when status=todo.
     */
    public function delete(User $user, ProjectTask $task): Response
    {
        $task->loadMissing('project');

        $profile = $user->staffMemberProfile;
        $isReviewer = $this->isReviewerRole($user);
        $isProjectLeader = $profile && $task->project?->project_leader_id === $profile->id;

        if (! $isReviewer && ! $isProjectLeader) {
            return Response::deny('Only manager/HR/project leader can delete tasks.');
        }

        $currentStatus = strtolower(trim((string) $task->status));
        if ($currentStatus !== TaskStatus::TODO->value) {
            return Response::deny('Tasks can only be deleted when status is "todo".');
        }

        return Response::allow();
    }

    /**
     * Determine if the user can add comments/attachments to a task.
     *
     * Flow:
     * - PL/Manager: can comment/attach when todo (setup) or review (feedback before reject)
     * - Staff assignee: can comment/attach when in_progress or rejected+needs_revision
     * - LOCKED for everyone: done, cancelled
     */
    public function collaborate(User $user, ProjectTask $task): Response
    {
        $task->loadMissing('project');

        $profile = $user->staffMemberProfile;
        if (! $profile) {
            return Response::deny('Unauthorized.');
        }

        $isReviewer = $this->isReviewerRole($user);
        $isProjectLeader = $task->project?->project_leader_id === $profile->id;
        $isPrivileged = $isReviewer || $isProjectLeader;
        $isAssignee = $task->assignee_id === $profile->id;

        $currentStatus = strtolower(trim((string) $task->status));

        // PL/Manager: can collaborate when todo (setup) or review (feedback before reject)
        if ($isPrivileged) {
            if (in_array($currentStatus, [
                TaskStatus::TODO->value,
                TaskStatus::REVIEW->value,
            ], true)) {
                return Response::allow();
            }

            return Response::deny('Task collaboration is only allowed when status is "todo" or "review" for managers.');
        }

        // Staff: must be assignee
        if (! $isAssignee) {
            return Response::deny('You can only collaborate on your own assigned tasks.');
        }

        // Staff: allowed during in_progress
        if ($currentStatus === TaskStatus::IN_PROGRESS->value) {
            return Response::allow();
        }

        // Staff: allowed during rejected + needs_revision
        if ($currentStatus === TaskStatus::REJECTED->value && $task->needs_revision) {
            return Response::allow();
        }

        return Response::deny('You can only add comments/attachments when working on the task.');
    }

    /**
     * Validate status transitions based on role.
     *
     * Staff transitions:
     *   todo → in_progress (start working)
     *   in_progress → review (submit for review)
     *   rejected → in_progress (start revision, only if needs_revision=true)
     *
     * PL/Manager transitions:
     *   review → done (approve)
     *   review → rejected (reject with reason)
     *   todo → cancelled (cancel before work starts)
     */
    public function transitionStatus(User $user, ProjectTask $task, string $from, string $to): Response
    {
        $profile = $user->staffMemberProfile;
        $isReviewer = $this->isReviewerRole($user);
        $isProjectLeader = $profile && $task->project?->project_leader_id === $profile->id;
        $isPrivileged = $isReviewer || $isProjectLeader;
        $isAssignee = $profile && $task->assignee_id === $profile->id;

        if ($from === $to) {
            return Response::allow();
        }

        // ─── Staff transitions ──────────────────────────────────────
        if (! $isPrivileged) {
            if (! $isAssignee) {
                return Response::deny('Only the assignee can change task status.');
            }

            $staffTransitions = [
                TaskStatus::TODO->value => [TaskStatus::IN_PROGRESS->value],
                TaskStatus::IN_PROGRESS->value => [TaskStatus::REVIEW->value],
                TaskStatus::REJECTED->value => [TaskStatus::IN_PROGRESS->value],
            ];

            $allowed = $staffTransitions[$from] ?? [];
            if (! in_array($to, $allowed, true)) {
                return Response::deny("You cannot transition task from \"{$from}\" to \"{$to}\".");
            }

            // rejected → in_progress only if needs_revision is true
            if ($from === TaskStatus::REJECTED->value && $to === TaskStatus::IN_PROGRESS->value) {
                if (! $task->needs_revision) {
                    return Response::deny('This task is not marked for revision.');
                }
            }

            return Response::allow();
        }

        // ─── PL/Manager transitions ────────────────────────────────
        $privilegedTransitions = [
            TaskStatus::REVIEW->value => [TaskStatus::DONE->value, TaskStatus::REJECTED->value],
            TaskStatus::TODO->value => [TaskStatus::CANCELLED->value],
        ];

        $allowed = $privilegedTransitions[$from] ?? [];
        if (! in_array($to, $allowed, true)) {
            return Response::deny("Invalid transition from \"{$from}\" to \"{$to}\" for reviewer.");
        }

        return Response::allow();
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    private function isReviewerRole(User $user): bool
    {
        return $user->hasRole('manager') || $user->hasRole('hr');
    }

    private function hasFieldChanged(string $field, array $data, ProjectTask $task): bool
    {
        if (! array_key_exists($field, $data)) {
            return false;
        }

        $incoming = $this->normalizeValue($data[$field]);
        $current = $this->normalizeValue($task->{$field});

        return $incoming !== $current;
    }

    private function normalizeValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }
}
