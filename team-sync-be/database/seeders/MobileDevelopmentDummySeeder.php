<?php

namespace Database\Seeders;

use App\Enums\Department;
use App\Enums\EmploymentType;
use App\Enums\JobStatus;
use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\SkillLevel;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TeamStatus;
use App\Enums\WorkLocation;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTeam;
use App\Models\StaffMemberProfile;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Seeder;

class MobileDevelopmentDummySeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
        ]);

        $teamBlueprints = $this->teamBlueprints();
        $employeeBlueprints = $this->employeeBlueprints();

        $teamsByStream = $this->seedTeams($teamBlueprints, $employeeBlueprints);
        $profilesByStream = $this->seedEmployees($employeeBlueprints, $teamsByStream);

        $this->assignTeamLeads($teamsByStream, $profilesByStream);
        $project = $this->seedProject($profilesByStream['pm'][0]['profile']);

        $this->attachTeamsToProject($project, $teamsByStream);
        $this->seedProjectTasks($project, $profilesByStream);

        $this->command?->info('LP3ES development dummy data generated successfully.');
        $this->command?->line('Project: '.$project->name);
        $this->command?->line('Total employees created/updated: '.count($employeeBlueprints));
        $this->command?->line('Team split: Frontend 6, Backend 2, UI/UX 1, QA 1, PM 1.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $employeeBlueprints
     * @return array<string, Team>
     */
    private function seedTeams(array $teamBlueprints, array $employeeBlueprints): array
    {
        $headcountByStream = [];
        foreach ($employeeBlueprints as $employeeBlueprint) {
            $stream = (string) $employeeBlueprint['stream'];
            $headcountByStream[$stream] = ($headcountByStream[$stream] ?? 0) + 1;
        }

        $teamsByStream = [];

        foreach ($teamBlueprints as $stream => $teamBlueprint) {
            $team = Team::withTrashed()->updateOrCreate(
                ['name' => $teamBlueprint['name']],
                [
                    'expected_size' => $headcountByStream[$stream] ?? $teamBlueprint['expected_size'],
                    'description' => $teamBlueprint['description'],
                    'icon' => $teamBlueprint['icon'],
                    'department' => $teamBlueprint['department'],
                    'status' => TeamStatus::ACTIVE->value,
                    'responsibilities' => $teamBlueprint['responsibilities'],
                ]
            );

            if ($team->trashed()) {
                $team->restore();
            }

            $teamsByStream[$stream] = $team;
        }

        return $teamsByStream;
    }

    /**
     * @param  array<int, array<string, mixed>>  $employeeBlueprints
     * @param  array<string, Team>  $teamsByStream
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function seedEmployees(array $employeeBlueprints, array $teamsByStream): array
    {
        $profilesByStream = [];

        foreach ($employeeBlueprints as $index => $employeeBlueprint) {
            $sequence = $index + 1;
            $stream = (string) $employeeBlueprint['stream'];
            $team = $teamsByStream[$stream];

            $user = User::withTrashed()->updateOrCreate(
                ['email' => $employeeBlueprint['email']],
                [
                    'name' => $employeeBlueprint['name'],
                    'password' => bcrypt('teamsync'),
                    'profile_photo' => $employeeBlueprint['profile_photo'] ?? null,
                ]
            );

            if ($user->trashed()) {
                $user->restore();
            }

            $profile = StaffMemberProfile::withTrashed()->updateOrCreate(
                ['code' => sprintf('MBD%03d', $sequence)],
                [
                    'user_id' => $user->id,
                    'identity_number' => $this->identityNumberFor($sequence),
                    'phone' => $this->phoneNumberFor($sequence),
                    'date_of_birth' => $employeeBlueprint['date_of_birth'],
                    'gender' => $employeeBlueprint['gender'],
                    'place_of_birth' => $employeeBlueprint['place_of_birth'],
                    'address' => $employeeBlueprint['address'],
                    'city' => $employeeBlueprint['city'],
                    'postal_code' => $employeeBlueprint['postal_code'],
                ]
            );

            if ($profile->trashed()) {
                $profile->restore();
            }

            $profile->jobInformation()->updateOrCreate(
                ['staff_member_id' => $profile->id],
                [
                    'staff_member_id' => $profile->id,
                    'job_title' => $employeeBlueprint['job_title'],
                    'team_id' => $team->id,
                    'status' => JobStatus::ACTIVE->value,
                    'employment_type' => EmploymentType::FULL_TIME->value,
                    'work_location' => WorkLocation::HYBRID->value,
                    'start_date' => now()->subMonths(6 + $sequence)->toDateString(),
                    'monthly_salary' => $employeeBlueprint['monthly_salary'],
                    'skill_level' => $employeeBlueprint['skill_level'] ?? SkillLevel::INTERMEDIATE->value,
                ]
            );

            $teamMember = TeamMember::withTrashed()->updateOrCreate(
                [
                    'team_id' => $team->id,
                    'staff_member_id' => $profile->id,
                ],
                [
                    'joined_at' => now()->subMonths(4 + $sequence),
                    'left_at' => null,
                ]
            );

            if ($teamMember->trashed()) {
                $teamMember->restore();
            }

            $user->syncRoles([$employeeBlueprint['role'] ?? 'staff']);

            $profilesByStream[$stream][] = [
                'user' => $user,
                'profile' => $profile,
            ];
        }

        return $profilesByStream;
    }

    /**
     * @param  array<string, Team>  $teamsByStream
     * @param  array<string, array<int, array<string, mixed>>>  $profilesByStream
     */
    private function assignTeamLeads(array $teamsByStream, array $profilesByStream): void
    {
        foreach ($teamsByStream as $stream => $team) {
            $lead = $profilesByStream[$stream][0] ?? null;
            if (! $lead) {
                continue;
            }

            $team->update([
                'team_lead_id' => $lead['user']->id,
            ]);
        }
    }

    private function seedProject(StaffMemberProfile $projectLeader): Project
    {
        $project = Project::withTrashed()->updateOrCreate(
            ['name' => 'LP3ES Development 11-Squad'],
            [
                'type' => ProjectType::MOBILE_APP->value,
                'priority' => ProjectPriority::HIGH->value,
                'status' => ProjectStatus::ACTIVE->value,
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->addMonths(6)->endOfMonth()->toDateString(),
                'description' => 'LP3ES mobile app development project with 11 members across FE, BE, UI/UX, QA, and PM.',
                'budget' => 2500000000,
                'project_leader_id' => $projectLeader->id,
            ]
        );

        if ($project->trashed()) {
            $project->restore();
        }

        return $project;
    }

    /**
     * @param  array<string, Team>  $teamsByStream
     */
    private function attachTeamsToProject(Project $project, array $teamsByStream): void
    {
        foreach ($teamsByStream as $team) {
            $projectTeam = ProjectTeam::withTrashed()->updateOrCreate(
                [
                    'project_id' => $project->id,
                    'team_id' => $team->id,
                ],
                [
                    'assigned_at' => now()->subMonths(2),
                ]
            );

            if ($projectTeam->trashed()) {
                $projectTeam->restore();
            }
        }
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $profilesByStream
     */
    private function seedProjectTasks(Project $project, array $profilesByStream): void
    {
        $taskBlueprints = $this->taskBlueprints();
        $assignmentCounters = [];
        $projectLeaderId = $project->project_leader_id;

        foreach ($taskBlueprints as $taskBlueprint) {
            $stream = $taskBlueprint['stream'];
            $assigneePool = $profilesByStream[$stream] ?? [];

            if ($assigneePool === []) {
                continue;
            }

            $currentIndex = $assignmentCounters[$stream] ?? 0;
            $assignee = $assigneePool[$currentIndex % count($assigneePool)]['profile'];
            $assignmentCounters[$stream] = $currentIndex + 1;

            $status = $taskBlueprint['status'];
            $isRejected = $status === TaskStatus::REJECTED->value;

            $task = ProjectTask::withTrashed()->updateOrCreate(
                [
                    'project_id' => $project->id,
                    'name' => $taskBlueprint['name'],
                ],
                [
                    'description' => $taskBlueprint['description'],
                    'assignee_id' => $assignee->id,
                    'priority' => $taskBlueprint['priority'],
                    'status' => $status,
                    'due_date' => now()->addDays($taskBlueprint['due_in_days'])->toDateString(),
                    'rejected_reason' => $isRejected ? ($taskBlueprint['rejected_reason'] ?? 'Need revision') : null,
                    'rejected_by' => $isRejected ? $projectLeaderId : null,
                    'rejected_at' => $isRejected ? now()->subDay() : null,
                ]
            );

            if ($task->trashed()) {
                $task->restore();
            }
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function teamBlueprints(): array
    {
        return [
            'frontend' => [
                'name' => 'Frontend Engineering',
                'expected_size' => 6,
                'description' => 'Builds mobile and web UI for the LP3ES application.',
                'icon' => 'team-icons/airplay.png',
                'department' => Department::DEVELOPMENT->value,
                'responsibilities' => [
                    'Build mobile app screens and components',
                    'Build web admin and public-facing pages',
                    'Integrate APIs and manage client state',
                ],
            ],
            'backend' => [
                'name' => 'Backend Engineering',
                'expected_size' => 2,
                'description' => 'Builds APIs, business logic, and data layer for the LP3ES platform.',
                'icon' => 'team-icons/activity.png',
                'department' => Department::DEVELOPMENT->value,
                'responsibilities' => [
                    'Design and maintain REST APIs',
                    'Handle authentication, notifications, and data sync',
                    'Ensure database integrity and query performance',
                ],
            ],
            'uiux' => [
                'name' => 'UI/UX Design',
                'expected_size' => 1,
                'description' => 'Designs product experience and visual assets for the LP3ES application.',
                'icon' => 'team-icons/pen-tool.png',
                'department' => Department::DESIGN->value,
                'responsibilities' => [
                    'Create wireframes and high-fidelity prototypes',
                    'Maintain design system and component specs',
                    'Run usability reviews and design QA',
                ],
            ],
            'qa' => [
                'name' => 'Quality Assurance',
                'expected_size' => 1,
                'description' => 'Owns test planning, regression, and release quality.',
                'icon' => 'team-icons/smile.png',
                'department' => Department::DEVELOPMENT->value,
                'responsibilities' => [
                    'Create test scenarios and acceptance criteria',
                    'Execute regression and release testing',
                    'Report defects with reproducible evidence',
                ],
            ],
            'pm' => [
                'name' => 'Project Management',
                'expected_size' => 1,
                'description' => 'Leads delivery planning and cross-team coordination.',
                'icon' => 'team-icons/key-round.png',
                'department' => Department::MANAGEMENT->value,
                'responsibilities' => [
                    'Define roadmap and sprint priorities',
                    'Manage cross-team dependencies and risks',
                    'Align stakeholders on scope and release goals',
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function employeeBlueprints(): array
    {
        return [
            [
                'stream' => 'pm',
                'name' => 'Hilmy Denta',
                'email' => 'hilmy.denta@teamsync.com',
                'job_title' => 'Project Manager',
                'gender' => 'male',
                'date_of_birth' => '1995-04-15',
                'place_of_birth' => 'Jakarta',
                'address' => 'Jl. Gatot Subroto No. 10',
                'city' => 'Jakarta',
                'postal_code' => '12930',
                'monthly_salary' => 22000000,
                'skill_level' => SkillLevel::EXPERT->value,
                'role' => 'manager',
                'profile_photo' => 'profile-pictures/male/1.avif',
            ],
            [
                'stream' => 'frontend',
                'name' => 'Reza Yudhistira',
                'email' => 'reza.yudhistira@teamsync.com',
                'job_title' => 'Lead Frontend Engineer',
                'gender' => 'male',
                'date_of_birth' => '1996-08-22',
                'place_of_birth' => 'Bandung',
                'address' => 'Jl. Dago No. 45',
                'city' => 'Bandung',
                'postal_code' => '40135',
                'monthly_salary' => 18000000,
                'skill_level' => SkillLevel::ADVANCED->value,
                'role' => 'staff',
                'profile_photo' => 'profile-pictures/male/2.avif',
            ],
            [
                'stream' => 'frontend',
                'name' => 'Agung Ramadhan',
                'email' => 'agung.ramadhan@teamsync.com',
                'job_title' => 'Frontend Mobile Engineer',
                'gender' => 'male',
                'date_of_birth' => '1998-01-10',
                'place_of_birth' => 'Bogor',
                'address' => 'Jl. Pajajaran No. 12',
                'city' => 'Bogor',
                'postal_code' => '16128',
                'monthly_salary' => 13000000,
                'skill_level' => SkillLevel::INTERMEDIATE->value,
                'role' => 'staff',
            ],
            [
                'stream' => 'frontend',
                'name' => 'Arkhab Agdana',
                'email' => 'arkhab.agdana@teamsync.com',
                'job_title' => 'Frontend Mobile Engineer',
                'gender' => 'male',
                'date_of_birth' => '1999-05-03',
                'place_of_birth' => 'Surabaya',
                'address' => 'Jl. Raya Darmo No. 8',
                'city' => 'Surabaya',
                'postal_code' => '60241',
                'monthly_salary' => 12500000,
                'skill_level' => SkillLevel::INTERMEDIATE->value,
                'role' => 'staff',
            ],
            [
                'stream' => 'frontend',
                'name' => 'Ronny',
                'email' => 'ronny@teamsync.com',
                'job_title' => 'Frontend Mobile Engineer',
                'gender' => 'male',
                'date_of_birth' => '1997-11-28',
                'place_of_birth' => 'Semarang',
                'address' => 'Jl. Pandanaran No. 20',
                'city' => 'Semarang',
                'postal_code' => '50134',
                'monthly_salary' => 12000000,
                'skill_level' => SkillLevel::INTERMEDIATE->value,
                'role' => 'staff',
            ],
            [
                'stream' => 'frontend',
                'name' => 'Hogie Britama',
                'email' => 'hogie.britama@teamsync.com',
                'job_title' => 'Frontend Web Admin Engineer',
                'gender' => 'male',
                'date_of_birth' => '1997-03-14',
                'place_of_birth' => 'Bekasi',
                'address' => 'Jl. Ahmad Yani No. 7',
                'city' => 'Bekasi',
                'postal_code' => '17143',
                'monthly_salary' => 13500000,
                'skill_level' => SkillLevel::INTERMEDIATE->value,
                'role' => 'staff',
            ],
            [
                'stream' => 'frontend',
                'name' => 'Daris',
                'email' => 'daris@teamsync.com',
                'job_title' => 'Frontend Web Engineer',
                'gender' => 'male',
                'date_of_birth' => '1998-07-19',
                'place_of_birth' => 'Depok',
                'address' => 'Jl. Margonda Raya No. 15',
                'city' => 'Depok',
                'postal_code' => '16424',
                'monthly_salary' => 12800000,
                'skill_level' => SkillLevel::INTERMEDIATE->value,
                'role' => 'staff',
            ],
            [
                'stream' => 'backend',
                'name' => 'Febriansyah',
                'email' => 'febriansyah@teamsync.com',
                'job_title' => 'Backend Engineer',
                'gender' => 'male',
                'date_of_birth' => '1996-02-18',
                'place_of_birth' => 'Tangerang',
                'address' => 'Jl. BSD Raya Utama No. 3',
                'city' => 'Tangerang',
                'postal_code' => '15345',
                'monthly_salary' => 15000000,
                'skill_level' => SkillLevel::ADVANCED->value,
                'role' => 'staff',
            ],
            [
                'stream' => 'backend',
                'name' => 'Muhammad Didi Majdi',
                'email' => 'didi.majdi@teamsync.com',
                'job_title' => 'Backend Engineer',
                'gender' => 'male',
                'date_of_birth' => '1997-09-25',
                'place_of_birth' => 'Surakarta',
                'address' => 'Jl. Slamet Riyadi No. 30',
                'city' => 'Surakarta',
                'postal_code' => '57141',
                'monthly_salary' => 14500000,
                'skill_level' => SkillLevel::INTERMEDIATE->value,
                'role' => 'staff',
            ],
            [
                'stream' => 'qa',
                'name' => 'Wiku',
                'email' => 'wiku@teamsync.com',
                'job_title' => 'QA Tester',
                'gender' => 'male',
                'date_of_birth' => '1998-06-08',
                'place_of_birth' => 'Yogyakarta',
                'address' => 'Jl. Kaliurang Km 5',
                'city' => 'Yogyakarta',
                'postal_code' => '55281',
                'monthly_salary' => 11000000,
                'skill_level' => SkillLevel::INTERMEDIATE->value,
                'role' => 'staff',
            ],
            [
                'stream' => 'uiux',
                'name' => 'Aufar',
                'email' => 'aufar@teamsync.com',
                'job_title' => 'UI/UX Designer',
                'gender' => 'male',
                'date_of_birth' => '1998-12-01',
                'place_of_birth' => 'Malang',
                'address' => 'Jl. Ijen No. 11',
                'city' => 'Malang',
                'postal_code' => '65119',
                'monthly_salary' => 12000000,
                'skill_level' => SkillLevel::INTERMEDIATE->value,
                'role' => 'staff',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function taskBlueprints(): array
    {
        return [
            [
                'stream' => 'pm',
                'name' => 'Finalize MVP scope and release milestones',
                'description' => 'Define release boundary, sprint cadence, and go-live criteria for mobile MVP.',
                'priority' => TaskPriority::HIGH->value,
                'status' => TaskStatus::IN_PROGRESS->value,
                'due_in_days' => 7,
            ],
            [
                'stream' => 'pm',
                'name' => 'Run weekly stakeholder sync and decision log',
                'description' => 'Document product decisions and unresolved blockers for LP3ES visibility.',
                'priority' => TaskPriority::MEDIUM->value,
                'status' => TaskStatus::TODO->value,
                'due_in_days' => 4,
            ],
            [
                'stream' => 'uiux',
                'name' => 'Build mobile design system token set',
                'description' => 'Create typography, spacing, and color tokens for native components.',
                'priority' => TaskPriority::HIGH->value,
                'status' => TaskStatus::IN_PROGRESS->value,
                'due_in_days' => 6,
            ],
            [
                'stream' => 'uiux',
                'name' => 'Design payroll and payslip mobile journey',
                'description' => 'Create end-to-end UX flow for payroll list and payslip details.',
                'priority' => TaskPriority::MEDIUM->value,
                'status' => TaskStatus::TODO->value,
                'due_in_days' => 11,
            ],
            [
                'stream' => 'frontend',
                'name' => 'Setup mobile app architecture and navigation shell',
                'description' => 'Initialize app modules, route guards, and feature folder structure.',
                'priority' => TaskPriority::HIGH->value,
                'status' => TaskStatus::IN_PROGRESS->value,
                'due_in_days' => 5,
            ],
            [
                'stream' => 'frontend',
                'name' => 'Implement authentication and session screens',
                'description' => 'Build sign-in, sign-out, and token refresh handling views.',
                'priority' => TaskPriority::HIGH->value,
                'status' => TaskStatus::REVIEW->value,
                'due_in_days' => 7,
            ],
            [
                'stream' => 'frontend',
                'name' => 'Integrate attendance API client into mobile app',
                'description' => 'Connect check-in/check-out and history endpoints with local state.',
                'priority' => TaskPriority::HIGH->value,
                'status' => TaskStatus::TODO->value,
                'due_in_days' => 8,
            ],
            [
                'stream' => 'frontend',
                'name' => 'Build leave request create and list screens',
                'description' => 'Create leave forms with validations and request timeline UI.',
                'priority' => TaskPriority::MEDIUM->value,
                'status' => TaskStatus::IN_PROGRESS->value,
                'due_in_days' => 10,
            ],
            [
                'stream' => 'frontend',
                'name' => 'Build web admin dashboard and payroll overview',
                'description' => 'Implement admin-facing pages for payroll list and employee summary.',
                'priority' => TaskPriority::HIGH->value,
                'status' => TaskStatus::TODO->value,
                'due_in_days' => 9,
            ],
            [
                'stream' => 'frontend',
                'name' => 'Build payslip detail mobile page',
                'description' => 'Implement payslip breakdown cards and downloadable proof section.',
                'priority' => TaskPriority::MEDIUM->value,
                'status' => TaskStatus::REVIEW->value,
                'due_in_days' => 9,
            ],
            [
                'stream' => 'backend',
                'name' => 'Design token refresh and device session API contract',
                'description' => 'Define payload and guard behavior for mobile authentication flows.',
                'priority' => TaskPriority::HIGH->value,
                'status' => TaskStatus::REVIEW->value,
                'due_in_days' => 6,
            ],
            [
                'stream' => 'backend',
                'name' => 'Optimize attendance summary query performance',
                'description' => 'Reduce response time for monthly attendance insights.',
                'priority' => TaskPriority::HIGH->value,
                'status' => TaskStatus::IN_PROGRESS->value,
                'due_in_days' => 7,
            ],
            [
                'stream' => 'qa',
                'name' => 'Build regression checklist for attendance module',
                'description' => 'Create positive and negative scenario matrix for attendance flows.',
                'priority' => TaskPriority::HIGH->value,
                'status' => TaskStatus::IN_PROGRESS->value,
                'due_in_days' => 6,
            ],
            [
                'stream' => 'qa',
                'name' => 'Execute smoke test on payroll mobile flow',
                'description' => 'Run smoke suite for payroll list and payslip detail behavior.',
                'priority' => TaskPriority::MEDIUM->value,
                'status' => TaskStatus::TODO->value,
                'due_in_days' => 7,
            ],
        ];
    }

    private function identityNumberFor(int $sequence): string
    {
        return str_pad((string) (3170000000000000 + $sequence), 16, '0', STR_PAD_LEFT);
    }

    private function phoneNumberFor(int $sequence): string
    {
        return '08131'.str_pad((string) (700000 + $sequence), 6, '0', STR_PAD_LEFT);
    }
}
