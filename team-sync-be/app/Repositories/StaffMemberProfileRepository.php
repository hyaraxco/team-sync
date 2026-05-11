<?php

namespace App\Repositories;

use App\Constants\CacheConstants;
use App\DTOs\EmergencyContactDto;
use App\DTOs\StaffMemberProfileDto;
use App\Interfaces\BankInformationRepositoryInterface;
use App\Interfaces\EmergencyContactRepositoryInterface;
use App\Interfaces\JobInformationRepositoryInterface;
use App\Interfaces\StaffMemberProfileRepositoryInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Models\BankInformation;
use App\Models\EmergencyContact;
use App\Models\JobInformation;
use App\Models\StaffMemberProfile;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Support\SensitiveData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StaffMemberProfileRepository implements StaffMemberProfileRepositoryInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private JobInformationRepositoryInterface $jobInformationRepository,
        private BankInformationRepositoryInterface $bankInformationRepository,
        private EmergencyContactRepositoryInterface $emergencyContactRepository
    ) {}

    public function getAll(
        ?string $search,
        ?string $status,
        ?string $type,
        ?string $workLocation,
        ?string $projectId,
        ?int $limit,
        bool $execute
    ): Builder|Collection {
        // If search is provided, use Scout for full-text search
        if ($search) {
            // Get IDs from Scout search first
            $scoutQuery = StaffMemberProfile::search($search);

            // Get the IDs from search results
            $searchResults = $scoutQuery->keys();

            // Build query with IDs and eager loading
            $query = StaffMemberProfile::with(['user', 'jobInformation', 'bankInformation', 'emergencyContacts'])
                ->whereIn('id', $searchResults);
        } else {
            // For non-search queries, use regular Eloquent
            $query = StaffMemberProfile::with(['user', 'jobInformation', 'bankInformation', 'emergencyContacts']);
        }

        // Apply filters (extracted to avoid duplication)
        $this->applyFilters($query, $status, $type, $workLocation, $projectId);

        $query->orderByDesc('created_at');

        if ($limit) {
            $query->take($limit);
        }

        if ($execute) {
            return $query->get();
        }

        return $query;
    }

    /**
     * Apply common filters to employee query
     */
    private function applyFilters($query, ?string $status, ?string $type, ?string $workLocation, ?string $projectId): void
    {
        if ($status) {
            $query->whereHas('jobInformation', function ($q) use ($status) {
                $q->where('status', $status);
            });
        }

        if ($type) {
            $query->whereHas('jobInformation', function ($q) use ($type) {
                $q->where('employment_type', $type);
            });
        }

        if ($workLocation) {
            $query->whereHas('jobInformation', function ($q) use ($workLocation) {
                $q->where('work_location', $workLocation);
            });
        }

        if ($projectId) {
            $query->where(function ($q) use ($projectId) {
                $q->whereHas('ledProjects', function ($leaderQ) use ($projectId) {
                    $leaderQ->where('projects.id', $projectId);
                });

                $q->orWhereHas('teamMembers', function ($tmQ) use ($projectId) {
                    $tmQ->whereNull('left_at')
                        ->whereHas('team.projects', function ($projQ) use ($projectId) {
                            $projQ->where('projects.id', $projectId);
                        });
                });
            });
        }
    }

    public function getAllPaginated(
        ?string $search,
        ?string $status,
        ?string $type,
        ?string $workLocation,
        ?string $projectId,
        int $rowPerPage
    ): LengthAwarePaginator {
        $query = $this->getAll(
            $search,
            $status,
            $type,
            $workLocation,
            $projectId,
            null,
            false
        );

        // Use regular pagination for all cases to ensure consistency
        return $query->paginate($rowPerPage);
    }

    public function getById(
        string $id
    ): StaffMemberProfile {
        return StaffMemberProfile::with([
            'user',
            'user.roles',
            'jobInformation.team.leader',
            'jobInformation.reviewTemplate',
            'jobInformation.team' => function ($query) {
                $query->withCount('members');
            },
            'bankInformation',
            'emergencyContacts',
        ])
            ->findOrFail($id);
    }

    public function getMyProfile(): StaffMemberProfile
    {
        $userId = Auth::id();

        return StaffMemberProfile::with([
            'user',
            'jobInformation.team.leader',
            'jobInformation.team' => function ($query) {
                $query->withCount('members');
            },
            'bankInformation',
            'emergencyContacts',
        ])
            ->where('user_id', $userId)
            ->firstOrFail();
    }

    public function create(array $data): StaffMemberProfile
    {
        return DB::transaction(function () use ($data) {
            $user = $this->createUser($data);
            $employee = $this->createStaffMemberProfile($data, $user->id);

            $this->createJobInformation($data, $employee->id);
            $this->createBankInformation($data, $employee->id);
            $this->createEmergencyContacts($data, $employee->id);
            $this->manageTeamMembership($employee->id, $data['team_id'] ?? null);

            // Clear statistics cache
            $this->clearEmployeeStatisticsCache();

            $employee = $employee->load(['user', 'jobInformation', 'bankInformation', 'emergencyContacts']);
            $employee->searchable();

            return $employee;
        });
    }

    public function update(string $id, array $data): StaffMemberProfile
    {
        return DB::transaction(function () use ($id, $data) {
            $employee = $this->getById($id);

            $this->updateUser($employee->user_id, $data);

            $employeeDto = StaffMemberProfileDto::fromArrayForUpdate($data, $employee);
            $employee->update($employeeDto->toArray());

            $this->updateJobInformation($employee->id, $data);
            $this->updateBankInformation($employee->id, $data);
            $this->updateEmergencyContacts($employee->id, $data);
            $this->manageTeamMembership($employee->id, $data['team_id'] ?? null);

            // Clear statistics cache
            $this->clearEmployeeStatisticsCache();

            $employee->searchable();

            return $employee;
        });
    }

    public function delete(string $id): StaffMemberProfile
    {
        return DB::transaction(function () use ($id) {
            $employee = $this->getById($id);

            $employee->delete();

            // Clear statistics cache
            $this->clearEmployeeStatisticsCache();

            return $employee;
        });
    }

    private function createUser(array $data)
    {
        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'roles' => $data['roles'] ?? [],
        ];

        if (isset($data['profile_photo'])) {
            $userData['profile_photo'] = $data['profile_photo'];
        }

        return $this->userRepository->create($userData);
    }

    private function createStaffMemberProfile(array $data, int $userId): StaffMemberProfile
    {
        $employeeCode = $this->generateEmployeeCode();

        $employeeData = array_merge($data, [
            'user_id' => $userId,
            'code' => $employeeCode,
        ]);

        $employeeDto = StaffMemberProfileDto::fromArray($employeeData);
        $employeeArray = $employeeDto->toArray();

        return StaffMemberProfile::create($employeeArray);
    }

    private function createJobInformation(array $data, int $employeeId): void
    {
        $jobData = [
            'staff_member_id' => $employeeId,
            'job_title' => $data['job_title'] ?? null,
            'team_id' => $data['team_id'] ?? null,
            'review_template_id' => $data['review_template_id'] ?? null,
            'status' => $data['status'] ?? null,
            'employment_type' => $data['employment_type'] ?? null,
            'work_location' => $data['work_location'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'monthly_salary' => $data['monthly_salary'] ?? null,
        ];

        $this->jobInformationRepository->create($jobData);
    }

    private function createBankInformation(array $data, int $employeeId): void
    {
        $bankData = [
            'staff_member_id' => $employeeId,
            'bank_name' => $data['bank_name'],
            'account_number' => $data['account_number'],
            'account_holder_name' => $data['account_holder_name'],
        ];

        $this->bankInformationRepository->create($bankData);
    }

    private function createEmergencyContacts(array $data, int $employeeId): void
    {
        foreach ($data['emergency_contacts'] as $contactData) {
            $contactData['staff_member_id'] = $employeeId;
            $emergencyContactDto = EmergencyContactDto::fromArray($contactData);

            $this->emergencyContactRepository->create($emergencyContactDto->toArray());
        }
    }

    private function generateEmployeeCode(): string
    {
        $year = now()->format('Y');
        $month = now()->format('m');

        $lastEmployee = StaffMemberProfile::where('code', 'like', "EMP{$year}{$month}%")
            ->orderBy('code', 'desc')
            ->first();

        if ($lastEmployee) {
            $lastSequence = (int) substr($lastEmployee->code, -4);
            $newSequence = $lastSequence + 1;
        } else {
            $newSequence = 1;
        }

        return sprintf('EMP%s%s%04d', $year, $month, $newSequence);
    }

    private function updateUser(int $userId, array $data): void
    {
        $fields = ['name', 'email', 'password', 'roles', 'profile_photo'];
        $userData = array_intersect_key($data, array_flip($fields));

        if ($userData) {
            $this->userRepository->update($userId, $userData);
        }
    }

    private function updateJobInformation(int $employeeId, array $data): void
    {
        $fields = [
            'job_title',
            'team_id',
            'review_template_id',
            'status',
            'employment_type',
            'work_location',
            'start_date',
            'monthly_salary',
        ];

        $jobData = array_intersect_key($data, array_flip($fields));

        if (empty($jobData)) {
            return;
        }

        $jobInfo = JobInformation::where('staff_member_id', $employeeId)->first();

        if ($jobInfo) {
            $this->jobInformationRepository->update($jobInfo->id, $jobData);
        } else {
            $this->createJobInformation($data, $employeeId);
        }
    }

    private function updateBankInformation(int $employeeId, array $data): void
    {
        $fields = [
            'bank_name',
            'account_number',
            'account_holder_name',
        ];

        $bankData = array_intersect_key($data, array_flip($fields));

        if (empty($bankData)) {
            return;
        }

        $bankInfo = BankInformation::where('staff_member_id', $employeeId)->first();

        if ($bankInfo) {
            $this->bankInformationRepository->update($bankInfo->id, $bankData);
        } else {
            $this->createBankInformation($data, $employeeId);
        }
    }

    private function updateEmergencyContacts(int $employeeId, array $data): void
    {
        $contacts = $data['emergency_contacts'] ?? null;
        if (! is_array($contacts) || empty($contacts)) {
            return;
        }

        $existingContactIds = EmergencyContact::where('staff_member_id', $employeeId)
            ->pluck('id')
            ->toArray();

        $submittedContactIds = [];

        foreach ($contacts as $contact) {
            $contact['staff_member_id'] = $employeeId;

            if (isset($contact['id']) && $contact['id']) {
                $existingContact = EmergencyContact::find($contact['id']);
                if ($existingContact) {
                    $emergencyContactDto = EmergencyContactDto::fromArrayForUpdate($contact, $existingContact);
                    $this->emergencyContactRepository->update($contact['id'], $emergencyContactDto->toArray());
                    $submittedContactIds[] = $contact['id'];
                }
            } else {
                $emergencyContactDto = EmergencyContactDto::fromArray($contact);
                $newContact = $this->emergencyContactRepository->create($emergencyContactDto->toArray());
                $submittedContactIds[] = $newContact->id;
            }
        }

        $contactsToDelete = array_diff($existingContactIds, $submittedContactIds);
        if (! empty($contactsToDelete)) {
            EmergencyContact::whereIn('id', $contactsToDelete)->delete();
        }
    }

    private function manageTeamMembership(int $employeeId, ?int $teamId): void
    {
        $currentMembership = TeamMember::where('staff_member_id', $employeeId)
            ->whereNull('left_at')
            ->first();

        $currentTeamId = $currentMembership?->team_id;

        if ($teamId === null) {
            if ($currentMembership) {
                $currentMembership->update(['left_at' => now()]);
            }

            return;
        }

        if ($currentTeamId === $teamId) {
            return;
        }

        if ($currentMembership) {
            $currentMembership->update(['left_at' => now()]);
        }

        TeamMember::updateOrCreate(
            [
                'team_id' => $teamId,
                'staff_member_id' => $employeeId,
            ],
            [
                'joined_at' => now(),
                'left_at' => null,
            ]
        );
    }

    public function getStatistics(): array
    {
        $cacheKey = CacheConstants::CACHE_KEY_EMPLOYEE_STATISTICS.now()->format('Y-m-d-H');

        return cache()->remember($cacheKey, CacheConstants::ONE_HOUR, function () {
            $lastWeekEnd = now()->subWeek()->endOfWeek();
            $currentMonth = now()->month;
            $currentYear = now()->year;

            // Single optimized query for employee counts
            $employeeStats = DB::table('staff_member_profiles')
                ->leftJoin('job_information', 'staff_member_profiles.id', '=', 'job_information.staff_member_id')
                ->selectRaw("
                    COUNT(DISTINCT staff_member_profiles.id) as total,
                    COUNT(DISTINCT CASE
                        WHEN MONTH(staff_member_profiles.created_at) = ?
                        AND YEAR(staff_member_profiles.created_at) = ?
                        THEN staff_member_profiles.id
                    END) as added_this_month,
                    COUNT(DISTINCT CASE
                        WHEN job_information.status = 'active'
                        THEN staff_member_profiles.id
                    END) as active,
                    COUNT(DISTINCT CASE
                        WHEN job_information.status = 'active'
                        AND staff_member_profiles.created_at <= ?
                        THEN staff_member_profiles.id
                    END) as active_last_week,
                    COUNT(DISTINCT CASE
                        WHEN job_information.status = 'on_leave'
                        THEN staff_member_profiles.id
                    END) as on_leave,
                    COUNT(DISTINCT CASE
                        WHEN job_information.status = 'on_leave'
                        AND staff_member_profiles.created_at <= ?
                        THEN staff_member_profiles.id
                    END) as on_leave_last_week,
                    AVG(job_information.monthly_salary) as average_salary
                ", [
                    $currentMonth,
                    $currentYear,
                    $lastWeekEnd,
                    $lastWeekEnd,
                ])
                ->first();

            return [
                'total' => (int) $employeeStats->total,
                'added_this_month' => (int) $employeeStats->added_this_month,
                'active' => (int) $employeeStats->active,
                'active_change' => (int) ($employeeStats->active - $employeeStats->active_last_week),
                'on_leave' => (int) $employeeStats->on_leave,
                'on_leave_change' => (int) ($employeeStats->on_leave - $employeeStats->on_leave_last_week),
                'average_salary' => round((float) ($employeeStats->average_salary ?? 0), 2),
                'new_employees' => (int) $employeeStats->added_this_month,
            ];
        });
    }

    /**
     * Clear employee statistics cache
     */
    private function clearEmployeeStatisticsCache(): void
    {
        cache()->forget(CacheConstants::CACHE_KEY_EMPLOYEE_STATISTICS.now()->format('Y-m-d-H'));
        cache()->forget(CacheConstants::CACHE_KEY_EMPLOYEE_TOTAL_COUNT);
    }

    public function getPerformanceStatistics(string $employeeId): array
    {
        $employee = StaffMemberProfile::findOrFail($employeeId);

        // Tasks Completed this month (from project_tasks)
        $tasksCompletedThisMonth = DB::table('project_tasks')
            ->where('assignee_id', $employeeId)
            ->where('status', 'done')
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->count();

        // Attendance Rate (percentage of days attended this month)
        $workingDaysThisMonth = now()->diffInDaysFiltered(function ($date) {
            return ! $date->isWeekend();
        }, now()->startOfMonth());

        $attendanceCount = DB::table('attendances')
            ->where('staff_member_id', $employeeId)
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->count();

        $attendanceRate = $workingDaysThisMonth > 0
            ? round(($attendanceCount / $workingDaysThisMonth) * 100, 1)
            : 0;

        // Projects count (active projects assigned to this employee via teams)
        $projectsCount = DB::table('team_members')
            ->join('teams', 'team_members.team_id', '=', 'teams.id')
            ->join('project_teams', 'teams.id', '=', 'project_teams.team_id')
            ->join('projects', 'project_teams.project_id', '=', 'projects.id')
            ->where('team_members.staff_member_id', $employeeId)
            ->where('projects.status', 'active')
            ->distinct()
            ->count('projects.id');

        // Performance Score (average based on tasks completion rate and attendance)
        // Simple calculation: (task completion rate + attendance rate) / 2
        $totalTasksThisMonth = DB::table('project_tasks')
            ->where('assignee_id', $employeeId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $taskCompletionRate = $totalTasksThisMonth > 0
            ? round(($tasksCompletedThisMonth / $totalTasksThisMonth) * 100, 1)
            : 0;

        $performanceScore = round(($taskCompletionRate + $attendanceRate) / 2, 1);

        return [
            'tasks_completed' => $tasksCompletedThisMonth,
            'attendance_rate' => $attendanceRate,
            'projects_count' => $projectsCount,
            'performance_score' => $performanceScore,
        ];
    }

    public function getMyTeam(): Team
    {
        $userId = Auth::id();

        $employee = StaffMemberProfile::with([
            'jobInformation.team.leader',
            'teamMembers.team.leader',
        ])
            ->where('user_id', $userId)
            ->firstOrFail();

        $team = $this->resolveEmployeeTeam($employee);

        if (! $team) {
            throw new \Exception('You are not assigned to any team');
        }

        $team->loadCount('members');

        return $team;
    }

    public function getMyTeamMembers(): Collection
    {
        $userId = Auth::id();

        $employee = StaffMemberProfile::with([
            'jobInformation.team',
            'teamMembers.team',
        ])
            ->where('user_id', $userId)
            ->firstOrFail();

        $team = $this->resolveEmployeeTeam($employee);

        if (! $team) {
            throw new \Exception('You are not assigned to any team');
        }

        return $team
            ->members()
            ->with([
                'staffMember.user',
                'staffMember.jobInformation',
            ])
            ->orderBy('joined_at', 'desc')
            ->get();
    }

    public function getMyTeamProjects(): Collection
    {
        $userId = Auth::id();

        $employee = StaffMemberProfile::with([
            'jobInformation.team',
            'teamMembers.team',
        ])
            ->where('user_id', $userId)
            ->firstOrFail();

        $team = $this->resolveEmployeeTeam($employee);

        if (! $team) {
            return collect();
        }

        return $team
            ->projects()
            ->with(['teams', 'projectLeader.user', 'projectLeader.jobInformation', 'tasks'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    private function resolveEmployeeTeam(StaffMemberProfile $employee): ?Team
    {
        $teamFromJobInfo = $employee->jobInformation?->team;
        if ($teamFromJobInfo) {
            return $teamFromJobInfo;
        }

        $activeMembership = TeamMember::with('team.leader')
            ->where('staff_member_id', $employee->id)
            ->whereNull('left_at')
            ->orderByDesc('joined_at')
            ->orderByDesc('id')
            ->first();

        if (! $activeMembership?->team) {
            return null;
        }

        if ($employee->jobInformation) {
            $employee->jobInformation->update([
                'team_id' => $activeMembership->team_id,
            ]);
        }

        return $activeMembership->team;
    }

    public function emailExists(string $email): bool
    {
        return User::query()->where('email', $email)->exists();
    }

    public function identityNumberExists(string $identityNumber): bool
    {
        return StaffMemberProfile::query()
            ->where('identity_number_hash', SensitiveData::hash($identityNumber))
            ->exists();
    }
}
