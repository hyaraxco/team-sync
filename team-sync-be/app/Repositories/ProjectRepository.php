<?php

namespace App\Repositories;

use App\Constants\CacheConstants;
use App\DTOs\ProjectDto;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Interfaces\ProjectRepositoryInterface;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\StaffMemberProfile;
use App\Models\TeamMember;
use App\Services\EmailService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProjectRepository implements ProjectRepositoryInterface
{
    public function __construct(
        private readonly EmailService $emailService
    ) {}

    public function getAll(
        ?string $search,
        ?string $status,
        ?int $limit,
        bool $execute
    ): Builder|Collection {
        $query = Project::with(['projectLeader', 'projectLeader.user', 'projectLeader.jobInformation', 'teams', 'tasks'])
            ->where(function ($query) use ($search, $status) {
                if ($search) {
                    $query->search($search);
                }

                if ($status) {
                    $query->where('status', $status);
                }
            })
            ->withCount('teams')
            ->withCount('tasks')
            ->orderByDesc('created_at');

        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        $isStaffUser = $currentUser
            && is_callable([$currentUser, 'hasRole'])
            && call_user_func([$currentUser, 'hasRole'], 'staff');

        if ($isStaffUser) {
            $profile = $currentUser->staffMemberProfile;
            if (! $profile) {
                return $query;
            }

            $employeeId = $profile->id;

            // Get team ID from JobInformation
            $jobInfoTeamId = $profile->jobInformation->team_id ?? null;

            // Get all team IDs that the employee is currently a member of (not left)
            $teamMemberIds = TeamMember::where('staff_member_id', $employeeId)
                ->whereNull('left_at')
                ->pluck('team_id')
                ->toArray();

            // Combine team IDs from JobInformation and TeamMember
            $teamIds = array_unique(array_filter(array_merge(
                $jobInfoTeamId ? [$jobInfoTeamId] : [],
                $teamMemberIds
            )));

            $query->where(function ($q) use ($employeeId, $teamIds) {
                // Show projects where employee is the leader
                $q->where('project_leader_id', $employeeId);

                // OR show projects where employee's team is assigned
                if (! empty($teamIds)) {
                    $q->orWhereHas('teams', function ($teamQuery) use ($teamIds) {
                        $teamQuery->whereIn('teams.id', $teamIds);
                    });
                }
            });
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
        ?string $status,
        int $rowPerPage
    ): LengthAwarePaginator {
        $query = $this->getAll(
            $search,
            $status,
            null,
            false
        );

        return $query->paginate($rowPerPage);
    }

    public function getById(
        string $id
    ): Project {
        return Project::with([
            'projectLeader',
            'projectLeader.user',
            'projectLeader.jobInformation',
            'teams' => function ($query) {
                $query->withCount('members');
            },
            'teams.leader',
            'tasks',
        ])
            ->findOrFail($id);
    }

    public function create(array $data): Project
    {
        return DB::transaction(function () use ($data) {
            $actorUserId = Auth::id();
            $actorName = Auth::user()?->name;

            $projectDto = ProjectDto::fromArray($data);
            $project = Project::create($projectDto->toArray());

            if (isset($data['photo'])) {
                $photoPath = $data['photo']->store('project-photos', 'public');
                $project->update(['photo' => $photoPath]);
            }

            $this->assignTeams($project->id, $data['teams'] ?? []);
            $this->createTasksFromTemplate($project->id, $data['task_template'] ?? null);

            $this->clearStatisticsCache();

            DB::afterCommit(function () use ($project, $actorUserId, $actorName) {
                $this->emailService->sendProjectLifecycleNotification(
                    $project,
                    'created',
                    null,
                    $actorUserId,
                    $actorName,
                );
            });

            return $project;
        });
    }

    public function update(string $id, array $data): Project
    {
        return DB::transaction(function () use ($id, $data) {
            $actorUserId = Auth::id();
            $actorName = Auth::user()?->name;

            $project = $this->getById($id);
            $previousStatus = (string) $project->status;

            $projectDto = ProjectDto::fromArrayForUpdate($data, $project);
            $project->update($projectDto->toArray());

            if (isset($data['photo'])) {
                if ($project->photo && Storage::disk('public')->exists($project->photo)) {
                    Storage::disk('public')->delete($project->photo);
                }

                $photoPath = $data['photo']->store('project-photos', 'public');
                $project->update(['photo' => $photoPath]);
            }

            $this->assignTeams($project->id, $data['teams'] ?? []);

            $this->clearStatisticsCache();

            if ((string) $project->status !== $previousStatus) {
                DB::afterCommit(function () use ($project, $previousStatus, $actorUserId, $actorName) {
                    $this->emailService->sendProjectLifecycleNotification(
                        $project,
                        'status_changed',
                        $previousStatus,
                        $actorUserId,
                        $actorName,
                    );
                });
            }

            return $project;
        });
    }

    public function delete(string $id): Project
    {
        return DB::transaction(function () use ($id) {
            $project = $this->getById($id);

            if ($project->photo && Storage::disk('public')->exists($project->photo)) {
                Storage::disk('public')->delete($project->photo);
            }

            $project->delete();

            $this->clearStatisticsCache();

            return $project;
        });
    }

    public function getStatistics(): array
    {
        // Cache key for statistics
        $cacheKey = CacheConstants::CACHE_KEY_PROJECT_STATISTICS.now()->format('Y-m-d-H');

        // Cache for 1 hour
        return cache()->remember($cacheKey, CacheConstants::ONE_HOUR, function () {
            // Get all project statistics in a single optimized query
            $projectStats = Project::selectRaw('
                COUNT(*) as total,
                COUNT(CASE WHEN status = ? THEN 1 END) as active,
                COUNT(CASE WHEN status = ? THEN 1 END) as completed,
                COUNT(CASE WHEN status = ? THEN 1 END) as on_hold,
                COUNT(CASE WHEN YEAR(created_at) = ? AND MONTH(created_at) = ? THEN 1 END) as added_this_month,
                COUNT(CASE WHEN status = ? AND created_at <= ? THEN 1 END) as active_last_week
            ', [
                'active',
                'completed',
                'on_hold',
                now()->year,
                now()->month,
                'active',
                now()->subWeek()->endOfWeek(),
            ])->first();

            // Get task statistics in a single optimized query
            $taskStats = DB::table('project_tasks')
                ->selectRaw('
                    COUNT(*) as total_tasks,
                    COUNT(CASE WHEN status = ? THEN 1 END) as completed_tasks,
                    COUNT(CASE WHEN status = ? THEN 1 END) as in_progress_tasks,
                    COUNT(CASE WHEN YEAR(created_at) = ? AND MONTH(created_at) = ? THEN 1 END) as tasks_this_month
                ', [
                    'done',
                    'in_progress',
                    now()->year,
                    now()->month,
                ])->first();

            $totalProjects = $projectStats->total;
            $activeProjects = $projectStats->active;
            $activeProjectsLastWeek = $projectStats->active_last_week;
            $projectsThisMonth = $projectStats->added_this_month;

            // Calculate changes
            $activeProjectsChange = $activeProjects - $activeProjectsLastWeek;

            // Calculate completion rate
            $completionRate = $taskStats->total_tasks > 0
                ? round(($taskStats->completed_tasks / $taskStats->total_tasks) * 100)
                : 0;

            return [
                'total' => $totalProjects,
                'active' => $activeProjects,
                'completed' => $projectStats->completed ?? 0,
                'on_hold' => $projectStats->on_hold ?? 0,
                'added_this_month' => $projectsThisMonth,
                'active_change' => $activeProjectsChange,
                'total_tasks' => $taskStats->total_tasks ?? 0,
                'completed_tasks' => $taskStats->completed_tasks ?? 0,
                'in_progress_tasks' => $taskStats->in_progress_tasks ?? 0,
                'tasks_this_month' => $taskStats->tasks_this_month ?? 0,
                'completion_rate' => $completionRate,
            ];
        });
    }

    public function getSquadSummary(string $id): array
    {
        $project = Project::with([
            'teams.members.staffMember.jobInformation.team',
            'tasks.assignee.jobInformation.team',
        ])->findOrFail($id);

        $members = $project->teams
            ->flatMap(fn ($team) => $team->members)
            ->map(fn ($member) => $member->staffMember)
            ->filter()
            ->unique('id')
            ->values();

        $streamKeys = ['frontend', 'backend', 'uiux', 'qa', 'pm', 'other'];

        $memberByStream = array_fill_keys($streamKeys, 0);
        foreach ($members as $member) {
            $stream = $this->resolveWorkStream($member);
            $memberByStream[$stream] = ($memberByStream[$stream] ?? 0) + 1;
        }

        $tasks = $project->tasks;
        $taskByStatus = array_fill_keys(array_column(TaskStatus::cases(), 'value'), 0);
        foreach ($tasks as $task) {
            $status = (string) $task->status;
            if (! array_key_exists($status, $taskByStatus)) {
                $taskByStatus[$status] = 0;
            }
            $taskByStatus[$status] += 1;
        }

        $taskByStream = array_fill_keys($streamKeys, 0);
        foreach ($tasks as $task) {
            $assignee = $task->assignee;
            $stream = $assignee ? $this->resolveWorkStream($assignee) : 'other';
            $taskByStream[$stream] = ($taskByStream[$stream] ?? 0) + 1;
        }

        $teamBreakdown = $project->teams
            ->map(function ($team): array {
                return [
                    'team_id' => $team->id,
                    'team_name' => $team->name,
                    'members_count' => $team->members
                        ->pluck('staff_member_id')
                        ->filter()
                        ->unique()
                        ->count(),
                ];
            })
            ->values()
            ->toArray();

        return [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'type' => $project->type,
                'priority' => $project->priority,
                'status' => $project->status,
            ],
            'headcount' => [
                'total' => $members->count(),
                'by_team' => $teamBreakdown,
                'by_stream' => $memberByStream,
            ],
            'tasks' => [
                'total' => $tasks->count(),
                'by_status' => $taskByStatus,
                'by_stream' => $taskByStream,
            ],
        ];
    }

    private function assignTeams(int $projectId, array $teamIds): void
    {
        $project = Project::findOrFail($projectId);

        if (empty($teamIds)) {
            $project->teams()->sync([]);

            return;
        }

        $syncPayload = [];
        foreach ($teamIds as $teamId) {
            $syncPayload[$teamId] = ['assigned_at' => now()];
        }

        $project->teams()->sync($syncPayload);
    }

    private function createTasksFromTemplate(int $projectId, ?string $taskTemplate): void
    {
        if (! $taskTemplate) {
            return;
        }

        $templateMap = $this->getTaskTemplateMap();
        $tasks = $templateMap[$taskTemplate] ?? [];

        foreach ($tasks as $task) {
            ProjectTask::create([
                'project_id' => $projectId,
                'name' => $task['name'],
                'description' => $task['description'] ?? null,
                'priority' => $task['priority'] ?? TaskPriority::MEDIUM->value,
                'status' => $task['status'] ?? TaskStatus::TODO->value,
                'assignee_id' => null,
            ]);
        }
    }

    private function getTaskTemplateMap(): array
    {
        return [
            'product_mvp' => [
                [
                    'name' => 'Kickoff and requirement alignment',
                    'description' => 'Align scope, timeline, and ownership across teams.',
                    'priority' => TaskPriority::HIGH->value,
                ],
                [
                    'name' => 'Create product specification',
                    'description' => 'Document goals, acceptance criteria, and user flows.',
                ],
                [
                    'name' => 'Implement core MVP features',
                    'description' => 'Build the minimum set of validated product features.',
                    'priority' => TaskPriority::HIGH->value,
                ],
                [
                    'name' => 'QA and regression testing',
                    'description' => 'Run verification and stabilize issues before release.',
                    'priority' => TaskPriority::HIGH->value,
                ],
                [
                    'name' => 'Release preparation and handoff',
                    'description' => 'Prepare release notes and handoff checklist.',
                ],
            ],
            'website_delivery' => [
                [
                    'name' => 'Site architecture and sitemap planning',
                    'description' => 'Define structure, pages, and navigation hierarchy.',
                ],
                [
                    'name' => 'UI/UX design and approval',
                    'description' => 'Finalize design assets and responsive layouts.',
                    'priority' => TaskPriority::HIGH->value,
                ],
                [
                    'name' => 'Frontend implementation',
                    'description' => 'Implement approved pages and reusable components.',
                    'priority' => TaskPriority::HIGH->value,
                ],
                [
                    'name' => 'CMS/content integration',
                    'description' => 'Integrate content and verify metadata.',
                ],
                [
                    'name' => 'Cross-browser QA and launch checklist',
                    'description' => 'Complete QA matrix and launch readiness checks.',
                    'priority' => TaskPriority::HIGH->value,
                ],
            ],
            'campaign_launch' => [
                [
                    'name' => 'Campaign brief and KPI setup',
                    'description' => 'Define target audience, goals, and success metrics.',
                    'priority' => TaskPriority::HIGH->value,
                ],
                [
                    'name' => 'Creative asset production',
                    'description' => 'Produce visuals and copy for all channels.',
                ],
                [
                    'name' => 'Landing page and tracking setup',
                    'description' => 'Configure conversion tracking and campaign links.',
                    'priority' => TaskPriority::HIGH->value,
                ],
                [
                    'name' => 'Campaign launch execution',
                    'description' => 'Launch campaign and monitor initial performance.',
                    'priority' => TaskPriority::HIGH->value,
                ],
                [
                    'name' => 'Post-launch performance reporting',
                    'description' => 'Summarize outcomes and optimization actions.',
                ],
            ],
        ];
    }

    private function clearStatisticsCache(): void
    {
        $cacheKey = CacheConstants::CACHE_KEY_PROJECT_STATISTICS.now()->format('Y-m-d-H');
        cache()->forget($cacheKey);
    }

    private function resolveWorkStream(StaffMemberProfile $employee): string
    {
        $teamName = strtolower((string) ($employee->jobInformation?->team?->name ?? ''));
        $jobTitle = strtolower((string) ($employee->jobInformation?->job_title ?? ''));
        $tokens = trim($teamName.' '.$jobTitle);

        if (str_contains($tokens, 'front')) {
            return 'frontend';
        }

        if (str_contains($tokens, 'back')) {
            return 'backend';
        }

        if (str_contains($tokens, 'ui/ux') || str_contains($tokens, 'uiux') || str_contains($tokens, 'ux')) {
            return 'uiux';
        }

        if (str_contains($tokens, 'qa') || str_contains($tokens, 'test')) {
            return 'qa';
        }

        if (str_contains($tokens, 'product manager') || str_contains($tokens, 'project manager') || str_contains($tokens, 'pm')) {
            return 'pm';
        }

        return 'other';
    }
}
