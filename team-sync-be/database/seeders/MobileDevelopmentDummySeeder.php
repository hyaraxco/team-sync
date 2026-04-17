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
use App\Models\EmployeeProfile;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTeam;
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

        $this->command?->info('Mobile development dummy data generated successfully.');
        $this->command?->line('Project: '.$project->name);
        $this->command?->line('Total employees created/updated: '.count($employeeBlueprints));
        $this->command?->line('Team split: Frontend 5, Backend 6, UI/UX 3, QA 4, PM 2.');
    }

    /**
     * @param array<int, array<string, mixed>> $employeeBlueprints
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
     * @param array<int, array<string, mixed>> $employeeBlueprints
     * @param array<string, Team> $teamsByStream
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

            $profile = EmployeeProfile::withTrashed()->updateOrCreate(
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
                ['employee_id' => $profile->id],
                [
                    'employee_id' => $profile->id,
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
                    'employee_id' => $profile->id,
                ],
                [
                    'joined_at' => now()->subMonths(4 + $sequence),
                    'left_at' => null,
                ]
            );

            if ($teamMember->trashed()) {
                $teamMember->restore();
            }

            $user->syncRoles([$employeeBlueprint['role'] ?? 'employee']);

            $profilesByStream[$stream][] = [
                'user' => $user,
                'profile' => $profile,
            ];
        }

        return $profilesByStream;
    }

    /**
     * @param array<string, Team> $teamsByStream
     * @param array<string, array<int, array<string, mixed>>> $profilesByStream
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

    private function seedProject(EmployeeProfile $projectLeader): Project
    {
        $project = Project::withTrashed()->updateOrCreate(
            ['name' => 'TeamSync Mobile Development 20 Squad'],
            [
                'type' => ProjectType::MOBILE_APP->value,
                'priority' => ProjectPriority::HIGH->value,
                'status' => ProjectStatus::ACTIVE->value,
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->addMonths(6)->endOfMonth()->toDateString(),
                'description' => 'Dummy mobile development project with 20 members split across FE, BE, UI/UX, QA, and PM teams.',
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
     * @param array<string, Team> $teamsByStream
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
     * @param array<string, array<int, array<string, mixed>>> $profilesByStream
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
                'name' => 'Mobile Frontend Engineering',
                'expected_size' => 5,
                'description' => 'Builds mobile UI and app interactions for iOS/Android user journeys.',
                'icon' => 'team-icons/airplay.png',
                'department' => Department::DEVELOPMENT->value,
                'responsibilities' => [
                    'Build mobile app screens and components',
                    'Integrate mobile APIs and state management',
                    'Optimize app performance and UX responsiveness',
                ],
            ],
            'backend' => [
                'name' => 'Mobile Backend Engineering',
                'expected_size' => 6,
                'description' => 'Builds APIs, business logic, and performance optimization for mobile modules.',
                'icon' => 'team-icons/activity.png',
                'department' => Department::DEVELOPMENT->value,
                'responsibilities' => [
                    'Design and maintain mobile APIs',
                    'Handle authentication, notifications, and data sync',
                    'Ensure scalability, logging, and observability',
                ],
            ],
            'uiux' => [
                'name' => 'Mobile UI/UX Design',
                'expected_size' => 3,
                'description' => 'Designs product experience, design systems, and visual consistency for mobile flows.',
                'icon' => 'team-icons/pen-tool.png',
                'department' => Department::DESIGN->value,
                'responsibilities' => [
                    'Create wireframes and high-fidelity prototypes',
                    'Maintain design system and component specs',
                    'Run usability reviews and design QA',
                ],
            ],
            'qa' => [
                'name' => 'Mobile QA Testing',
                'expected_size' => 4,
                'description' => 'Owns test planning, regression, and release quality for mobile delivery.',
                'icon' => 'team-icons/smile.png',
                'department' => Department::DEVELOPMENT->value,
                'responsibilities' => [
                    'Create test scenarios and acceptance criteria',
                    'Execute regression and release testing',
                    'Report defects with reproducible evidence',
                ],
            ],
            'pm' => [
                'name' => 'Mobile Product Management',
                'expected_size' => 2,
                'description' => 'Leads roadmap, delivery planning, and cross-team project coordination.',
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
                'name' => 'Raka Pratama',
                'email' => 'mobile.pm01@teamsync.com',
                'job_title' => 'Product Manager',
                'gender' => 'male',
                'date_of_birth' => '1990-03-12',
                'place_of_birth' => 'Bandung',
                'address' => 'Jl. Sukajadi No. 18',
                'city' => 'Bandung',
                'postal_code' => '40162',
                'monthly_salary' => 26000000,
                'skill_level' => SkillLevel::EXPERT->value,
                'role' => 'manager',
                'profile_photo' => 'profile-pictures/male/1.avif',
            ],
            [
                'stream' => 'pm',
                'name' => 'Nadia Putri',
                'email' => 'mobile.pm02@teamsync.com',
                'job_title' => 'Associate Product Manager',
                'gender' => 'female',
                'date_of_birth' => '1994-08-09',
                'place_of_birth' => 'Yogyakarta',
                'address' => 'Jl. Kaliurang Km 7',
                'city' => 'Yogyakarta',
                'postal_code' => '55281',
                'monthly_salary' => 19000000,
                'skill_level' => SkillLevel::ADVANCED->value,
                'role' => 'employee',
                'profile_photo' => 'profile-pictures/female/1.avif',
            ],
            [
                'stream' => 'uiux',
                'name' => 'Alia Salsabila',
                'email' => 'mobile.uiux01@teamsync.com',
                'job_title' => 'Senior UI/UX Designer',
                'gender' => 'female',
                'date_of_birth' => '1993-11-02',
                'place_of_birth' => 'Semarang',
                'address' => 'Jl. Pandanaran No. 77',
                'city' => 'Semarang',
                'postal_code' => '50134',
                'monthly_salary' => 17000000,
                'skill_level' => SkillLevel::ADVANCED->value,
                'role' => 'employee',
            ],
            [
                'stream' => 'uiux',
                'name' => 'Dimas Kurniawan',
                'email' => 'mobile.uiux02@teamsync.com',
                'job_title' => 'Product Designer',
                'gender' => 'male',
                'date_of_birth' => '1996-05-18',
                'place_of_birth' => 'Malang',
                'address' => 'Jl. Ijen No. 5',
                'city' => 'Malang',
                'postal_code' => '65119',
                'monthly_salary' => 13500000,
                'skill_level' => SkillLevel::INTERMEDIATE->value,
                'role' => 'employee',
            ],
            [
                'stream' => 'uiux',
                'name' => 'Citra Maharani',
                'email' => 'mobile.uiux03@teamsync.com',
                'job_title' => 'UX Researcher',
                'gender' => 'female',
                'date_of_birth' => '1997-01-24',
                'place_of_birth' => 'Surabaya',
                'address' => 'Jl. Diponegoro No. 23',
                'city' => 'Surabaya',
                'postal_code' => '60241',
                'monthly_salary' => 12000000,
                'skill_level' => SkillLevel::INTERMEDIATE->value,
                'role' => 'employee',
            ],
            [
                'stream' => 'frontend',
                'name' => 'Fajar Nugroho',
                'email' => 'mobile.fe01@teamsync.com',
                'job_title' => 'Senior Frontend Engineer',
                'gender' => 'male',
                'date_of_birth' => '1992-06-15',
                'place_of_birth' => 'Jakarta',
                'address' => 'Jl. Kemang Raya No. 10',
                'city' => 'Jakarta',
                'postal_code' => '12730',
                'monthly_salary' => 18500000,
                'skill_level' => SkillLevel::ADVANCED->value,
                'role' => 'employee',
            ],
            [
                'stream' => 'frontend',
                'name' => 'Luthfi Ramadhan',
                'email' => 'mobile.fe02@teamsync.com',
                'job_title' => 'Frontend Engineer',
                'gender' => 'male',
                'date_of_birth' => '1997-09-03',
                'place_of_birth' => 'Bekasi',
                'address' => 'Jl. Ahmad Yani No. 4',
                'city' => 'Bekasi',
                'postal_code' => '17143',
                'monthly_salary' => 12800000,
                'skill_level' => SkillLevel::INTERMEDIATE->value,
                'role' => 'employee',
            ],
            [
                'stream' => 'frontend',
                'name' => 'Maya Anindita',
                'email' => 'mobile.fe03@teamsync.com',
                'job_title' => 'Frontend Engineer',
                'gender' => 'female',
                'date_of_birth' => '1998-02-11',
                'place_of_birth' => 'Bogor',
                'address' => 'Jl. Pajajaran No. 6',
                'city' => 'Bogor',
                'postal_code' => '16128',
                'monthly_salary' => 11800000,
                'skill_level' => SkillLevel::INTERMEDIATE->value,
                'role' => 'employee',
            ],
            [
                'stream' => 'frontend',
                'name' => 'Rizky Saputra',
                'email' => 'mobile.fe04@teamsync.com',
                'job_title' => 'Mobile Frontend Engineer',
                'gender' => 'male',
                'date_of_birth' => '1996-12-20',
                'place_of_birth' => 'Depok',
                'address' => 'Jl. Margonda Raya No. 81',
                'city' => 'Depok',
                'postal_code' => '16424',
                'monthly_salary' => 13200000,
                'skill_level' => SkillLevel::INTERMEDIATE->value,
                'role' => 'employee',
            ],
            [
                'stream' => 'frontend',
                'name' => 'Ayu Lestari',
                'email' => 'mobile.fe05@teamsync.com',
                'job_title' => 'Junior Frontend Engineer',
                'gender' => 'female',
                'date_of_birth' => '1999-07-22',
                'place_of_birth' => 'Sleman',
                'address' => 'Jl. Magelang Km 5',
                'city' => 'Sleman',
                'postal_code' => '55284',
                'monthly_salary' => 9800000,
                'skill_level' => SkillLevel::BEGINNER->value,
                'role' => 'employee',
            ],
            [
                'stream' => 'backend',
                'name' => 'Bagas Wicaksono',
                'email' => 'mobile.be01@teamsync.com',
                'job_title' => 'Senior Backend Engineer',
                'gender' => 'male',
                'date_of_birth' => '1991-01-17',
                'place_of_birth' => 'Surakarta',
                'address' => 'Jl. Slamet Riyadi No. 24',
                'city' => 'Surakarta',
                'postal_code' => '57141',
                'monthly_salary' => 19500000,
                'skill_level' => SkillLevel::EXPERT->value,
                'role' => 'employee',
            ],
            [
                'stream' => 'backend',
                'name' => 'Kevin Prakoso',
                'email' => 'mobile.be02@teamsync.com',
                'job_title' => 'Backend Engineer',
                'gender' => 'male',
                'date_of_birth' => '1995-04-27',
                'place_of_birth' => 'Tangerang',
                'address' => 'Jl. BSD Raya Utama',
                'city' => 'Tangerang',
                'postal_code' => '15345',
                'monthly_salary' => 15000000,
                'skill_level' => SkillLevel::ADVANCED->value,
                'role' => 'employee',
            ],
            [
                'stream' => 'backend',
                'name' => 'Sinta Puspita',
                'email' => 'mobile.be03@teamsync.com',
                'job_title' => 'Backend Engineer',
                'gender' => 'female',
                'date_of_birth' => '1996-09-12',
                'place_of_birth' => 'Bandar Lampung',
                'address' => 'Jl. ZA Pagar Alam No. 10',
                'city' => 'Bandar Lampung',
                'postal_code' => '35142',
                'monthly_salary' => 14200000,
                'skill_level' => SkillLevel::INTERMEDIATE->value,
                'role' => 'employee',
            ],
            [
                'stream' => 'backend',
                'name' => 'Yusuf Maulana',
                'email' => 'mobile.be04@teamsync.com',
                'job_title' => 'API Engineer',
                'gender' => 'male',
                'date_of_birth' => '1998-10-05',
                'place_of_birth' => 'Padang',
                'address' => 'Jl. Khatib Sulaiman No. 9',
                'city' => 'Padang',
                'postal_code' => '25173',
                'monthly_salary' => 11800000,
                'skill_level' => SkillLevel::INTERMEDIATE->value,
                'role' => 'employee',
            ],
            [
                'stream' => 'backend',
                'name' => 'Dewi Anggraini',
                'email' => 'mobile.be05@teamsync.com',
                'job_title' => 'Backend Engineer',
                'gender' => 'female',
                'date_of_birth' => '1997-03-01',
                'place_of_birth' => 'Medan',
                'address' => 'Jl. Gatot Subroto No. 14',
                'city' => 'Medan',
                'postal_code' => '20119',
                'monthly_salary' => 12600000,
                'skill_level' => SkillLevel::INTERMEDIATE->value,
                'role' => 'employee',
            ],
            [
                'stream' => 'backend',
                'name' => 'Hendra Wijaya',
                'email' => 'mobile.be06@teamsync.com',
                'job_title' => 'Junior Backend Engineer',
                'gender' => 'male',
                'date_of_birth' => '1999-12-30',
                'place_of_birth' => 'Palembang',
                'address' => 'Jl. Jendral Sudirman No. 3',
                'city' => 'Palembang',
                'postal_code' => '30126',
                'monthly_salary' => 9800000,
                'skill_level' => SkillLevel::BEGINNER->value,
                'role' => 'employee',
            ],
            [
                'stream' => 'qa',
                'name' => 'Tio Mahendra',
                'email' => 'mobile.qa01@teamsync.com',
                'job_title' => 'Senior QA Tester',
                'gender' => 'male',
                'date_of_birth' => '1993-08-13',
                'place_of_birth' => 'Batam',
                'address' => 'Jl. Nagoya No. 11',
                'city' => 'Batam',
                'postal_code' => '29444',
                'monthly_salary' => 14800000,
                'skill_level' => SkillLevel::ADVANCED->value,
                'role' => 'employee',
            ],
            [
                'stream' => 'qa',
                'name' => 'Nina Kartika',
                'email' => 'mobile.qa02@teamsync.com',
                'job_title' => 'QA Automation Engineer',
                'gender' => 'female',
                'date_of_birth' => '1996-06-21',
                'place_of_birth' => 'Pekanbaru',
                'address' => 'Jl. Sudirman No. 35',
                'city' => 'Pekanbaru',
                'postal_code' => '28116',
                'monthly_salary' => 13200000,
                'skill_level' => SkillLevel::INTERMEDIATE->value,
                'role' => 'employee',
            ],
            [
                'stream' => 'qa',
                'name' => 'Galih Permana',
                'email' => 'mobile.qa03@teamsync.com',
                'job_title' => 'QA Tester',
                'gender' => 'male',
                'date_of_birth' => '1998-04-04',
                'place_of_birth' => 'Cirebon',
                'address' => 'Jl. Kartini No. 2',
                'city' => 'Cirebon',
                'postal_code' => '45122',
                'monthly_salary' => 10800000,
                'skill_level' => SkillLevel::INTERMEDIATE->value,
                'role' => 'employee',
            ],
            [
                'stream' => 'qa',
                'name' => 'Vina Oktaviani',
                'email' => 'mobile.qa04@teamsync.com',
                'job_title' => 'Junior QA Tester',
                'gender' => 'female',
                'date_of_birth' => '1999-11-14',
                'place_of_birth' => 'Banjarmasin',
                'address' => 'Jl. A. Yani Km 4',
                'city' => 'Banjarmasin',
                'postal_code' => '70234',
                'monthly_salary' => 9200000,
                'skill_level' => SkillLevel::BEGINNER->value,
                'role' => 'employee',
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
                'name' => 'Groom sprint backlog for mobile squad',
                'description' => 'Prioritize cross-team backlog items and lock sprint goals.',
                'priority' => TaskPriority::HIGH->value,
                'status' => TaskStatus::TODO->value,
                'due_in_days' => 5,
            ],
            [
                'stream' => 'pm',
                'name' => 'Prepare risk register and mitigation plan',
                'description' => 'Track dependency risks across FE, BE, UI/UX, and QA streams.',
                'priority' => TaskPriority::MEDIUM->value,
                'status' => TaskStatus::REVIEW->value,
                'due_in_days' => 9,
            ],
            [
                'stream' => 'pm',
                'name' => 'Run weekly stakeholder sync and decision log',
                'description' => 'Document product decisions and unresolved blockers for leadership visibility.',
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
                'name' => 'Create onboarding and login high-fidelity flow',
                'description' => 'Deliver complete screens for authentication and first-time onboarding.',
                'priority' => TaskPriority::HIGH->value,
                'status' => TaskStatus::REVIEW->value,
                'due_in_days' => 5,
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
                'stream' => 'uiux',
                'name' => 'Revise attendance timeline interaction',
                'description' => 'Improve tap target and timeline readability based on review feedback.',
                'priority' => TaskPriority::MEDIUM->value,
                'status' => TaskStatus::REJECTED->value,
                'due_in_days' => 3,
                'rejected_reason' => 'Interaction pattern still inconsistent with design system guideline.',
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
                'name' => 'Implement notification center and deep-link handling',
                'description' => 'Render notification feed and route users to related feature detail pages.',
                'priority' => TaskPriority::MEDIUM->value,
                'status' => TaskStatus::TODO->value,
                'due_in_days' => 6,
            ],
            [
                'stream' => 'frontend',
                'name' => 'Add offline cache strategy for dashboard snapshots',
                'description' => 'Cache latest dashboard data for unstable network scenarios.',
                'priority' => TaskPriority::MEDIUM->value,
                'status' => TaskStatus::TODO->value,
                'due_in_days' => 12,
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
                'stream' => 'frontend',
                'name' => 'Revise rejected payroll card interaction',
                'description' => 'Adjust card behavior and messaging after PM review feedback.',
                'priority' => TaskPriority::LOW->value,
                'status' => TaskStatus::REJECTED->value,
                'due_in_days' => 4,
                'rejected_reason' => 'Card states are inconsistent with approved interaction spec.',
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
                'name' => 'Implement device session revocation endpoint',
                'description' => 'Allow users to terminate stale sessions from mobile settings.',
                'priority' => TaskPriority::HIGH->value,
                'status' => TaskStatus::IN_PROGRESS->value,
                'due_in_days' => 8,
            ],
            [
                'stream' => 'backend',
                'name' => 'Create notification batching endpoint for mobile',
                'description' => 'Support efficient loading and pagination for notification feed.',
                'priority' => TaskPriority::MEDIUM->value,
                'status' => TaskStatus::TODO->value,
                'due_in_days' => 10,
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
                'stream' => 'backend',
                'name' => 'Implement payroll mobile summary endpoint',
                'description' => 'Provide compact payroll summary for mobile dashboard cards.',
                'priority' => TaskPriority::MEDIUM->value,
                'status' => TaskStatus::TODO->value,
                'due_in_days' => 11,
            ],
            [
                'stream' => 'backend',
                'name' => 'Add audit logging for mobile critical actions',
                'description' => 'Capture and tag important mobile interactions for monitoring.',
                'priority' => TaskPriority::MEDIUM->value,
                'status' => TaskStatus::REVIEW->value,
                'due_in_days' => 9,
            ],
            [
                'stream' => 'backend',
                'name' => 'Harden API rate limiting for mobile endpoints',
                'description' => 'Set endpoint-level limits and fallback responses.',
                'priority' => TaskPriority::MEDIUM->value,
                'status' => TaskStatus::TODO->value,
                'due_in_days' => 12,
            ],
            [
                'stream' => 'backend',
                'name' => 'Prepare API readiness checklist for release candidate',
                'description' => 'Validate endpoint contracts and payload compatibility for mobile app.',
                'priority' => TaskPriority::LOW->value,
                'status' => TaskStatus::TODO->value,
                'due_in_days' => 13,
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
                'name' => 'Write API test cases for authentication endpoints',
                'description' => 'Cover login, refresh token, and unauthorized access scenarios.',
                'priority' => TaskPriority::HIGH->value,
                'status' => TaskStatus::TODO->value,
                'due_in_days' => 8,
            ],
            [
                'stream' => 'qa',
                'name' => 'Execute smoke test on payroll mobile flow',
                'description' => 'Run smoke suite for payroll list and payslip detail behavior.',
                'priority' => TaskPriority::MEDIUM->value,
                'status' => TaskStatus::REVIEW->value,
                'due_in_days' => 7,
            ],
            [
                'stream' => 'qa',
                'name' => 'Verify deep-link routing from notifications',
                'description' => 'Validate route landing for task, payroll, and attendance notifications.',
                'priority' => TaskPriority::MEDIUM->value,
                'status' => TaskStatus::TODO->value,
                'due_in_days' => 9,
            ],
            [
                'stream' => 'qa',
                'name' => 'Test offline synchronization conflict scenarios',
                'description' => 'Simulate unstable network and verify conflict resolution behavior.',
                'priority' => TaskPriority::MEDIUM->value,
                'status' => TaskStatus::TODO->value,
                'due_in_days' => 12,
            ],
            [
                'stream' => 'qa',
                'name' => 'Validate role-based access matrix for mobile',
                'description' => 'Ensure manager, HR, finance, and employee role boundaries are correct.',
                'priority' => TaskPriority::HIGH->value,
                'status' => TaskStatus::IN_PROGRESS->value,
                'due_in_days' => 10,
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
