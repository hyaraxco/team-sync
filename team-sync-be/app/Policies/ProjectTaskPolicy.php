<?php

namespace App\Policies;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProjectTaskPolicy
{
    /**
     * Determine if the user can view any tasks (index/list).
     * Actual scoping is done in the repository via applyCurrentUserReadScope.
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

        if (! $this->isProjectMember($user, $task->project)) {
            return Response::deny('You can only view tasks in projects you are a member of.');
        }

        return Response::allow();
    }

    /**
     * Determine if the user can create a task.
     * Staff: must be project member, can only self-assign, status must be 'todo'.
     */
    public function create(User $user, array $data = []): Response
    {
        $profile = $user->staffMemberProfile;
        if (! $profile) {
            return Response::deny('Your account is not linked to an employee profile.');
        }

        if ($this->isReviewerRole($user)) {
            return Response::allow();
        }

        // Pure staff checks
        $projectId = $data['project_id'] ?? null;
        if (! $projectId) {
            return Response::deny('Project ID is required.');
        }

        $project = Project::with('teams')->find($projectId);
        if (! $project) {
            return Response::deny('Project not found.');
        }

        if (! $this->isProjectMember($user, $project)) {
            return Response::deny('You can only create tasks in projects you are a member of.');
        }

        // Staff cannot assign to others
        $assigneeId = $data['assignee_id'] ?? null;
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
     * Staff: must be assignee or project leader.
     */
    public function update(User $user, ProjectTask $task): Response
    {
        if ($this->isReviewerRole($user)) {
            return Response::allow();
        }

        $profile = $user->staffMemberProfile;
        if (! $profile) {
            return Response::deny('Your account is not linked to an employee profile.');
        }

        $isAssignee = $task->assignee_id === $profile->id;
        $isProjectLeader = $task->project?->project_leader_id === $profile->id;

        if (! $isAssignee && ! $isProjectLeader) {
            return Response::deny('You can only update your own assigned tasks.');
        }

        return Response::allow();
    }

    /**
     * Determine if the user can reassign a task.
     * Only manager/HR/project leader can change assignee.
     */
    public function reassign(User $user, ProjectTask $task): Response
    {
        if ($this->isReviewerRole($user)) {
            return Response::allow();
        }

        $profile = $user->staffMemberProfile;
        $isProjectLeader = $profile && $task->project?->project_leader_id === $profile->id;

        if ($isProjectLeader) {
            return Response::allow();
        }

        return Response::deny('Only manager/HR/project leader can reassign task assignee.');
    }

    /**
     * Determine if the user can delete a task.
     * Only manager can delete.
     */
    public function delete(User $user, ProjectTask $task): Response
    {
        if ($user->hasRole('manager')) {
            return Response::allow();
        }

        return Response::deny('Only manager can delete tasks.');
    }

    /**
     * Determine if the user can add comments/attachments to a task.
     * Staff: must be assignee, task must not be in review/done/cancelled.
     */
    public function collaborate(User $user, ProjectTask $task): Response
    {
        $profile = $user->staffMemberProfile;
        if (! $profile) {
            return Response::deny('Unauthorized.');
        }

        if ($this->isReviewerRole($user)) {
            return Response::allow();
        }

        $isProjectLeader = $task->project?->project_leader_id === $profile->id;
        if ($isProjectLeader) {
            return Response::allow();
        }

        $isAssignee = $task->assignee_id === $profile->id;
        if (! $isAssignee) {
            return Response::deny('You can only collaborate on your own assigned tasks.');
        }

        $lockedStatuses = [
            TaskStatus::REVIEW->value,
            TaskStatus::DONE->value,
            TaskStatus::CANCELLED->value,
        ];

        $currentStatus = strtolower(trim((string) $task->status));
        if (in_array($currentStatus, $lockedStatuses, true)) {
            return Response::deny('Task is locked for this action in current status.');
        }

        return Response::allow();
    }

    /**
     * Determine if the user can transition a task's status.
     * Validates allowed transitions per role.
     */
    public function transitionStatus(User $user, ProjectTask $task, string $from, string $to): Response
    {
        $isReviewer = $this->isReviewerRole($user);
        $isPureEmployee = $user->hasRole('staff') && ! $isReviewer;

        if ($isPureEmployee) {
            $employeeTransitions = [
                TaskStatus::TODO->value => [TaskStatus::IN_PROGRESS->value],
                TaskStatus::IN_PROGRESS->value => [TaskStatus::REVIEW->value],
                TaskStatus::REJECTED->value => [TaskStatus::IN_PROGRESS->value],
            ];

            $allowed = $employeeTransitions[$from] ?? [];
            if (! in_array($to, $allowed, true)) {
                return Response::deny("Invalid status transition from {$from} to {$to}.");
            }
        }

        if ($isReviewer) {
            $reviewerTransitions = [
                TaskStatus::REVIEW->value => [TaskStatus::DONE->value, TaskStatus::REJECTED->value],
                TaskStatus::DONE->value => [TaskStatus::REJECTED->value],
            ];

            if ($from !== $to) {
                $allowed = $reviewerTransitions[$from] ?? [];
                if (! in_array($to, $allowed, true)) {
                    return Response::deny("Invalid reviewer status transition from {$from} to {$to}.");
                }
            }
        }

        return Response::allow();
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    private function isReviewerRole(User $user): bool
    {
        return $user->hasRole('manager') || $user->hasRole('hr');
    }

    private function isProjectMember(User $user, ?Project $project): bool
    {
        if (! $project) {
            return false;
        }

        $profile = $user->staffMemberProfile;
        if (! $profile) {
            return false;
        }

        // Project leader is always a member
        if ($project->project_leader_id === $profile->id) {
            return true;
        }

        // Check team membership
        $jobInfoTeamId = $profile->jobInformation->team_id ?? null;
        $teamMemberIds = TeamMember::where('staff_member_id', $profile->id)
            ->whereNull('left_at')
            ->pluck('team_id')
            ->toArray();

        $teamIds = array_unique(array_filter(array_merge(
            $jobInfoTeamId ? [$jobInfoTeamId] : [],
            $teamMemberIds
        )));

        $projectTeamIds = $project->teams->pluck('id')->toArray();

        return ! empty(array_intersect($projectTeamIds, $teamIds));
    }
}
