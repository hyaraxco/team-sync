<?php

namespace Database\Seeders;

use App\Enums\Department;
use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\TeamStatus;
use App\Models\Project;
use App\Models\ProjectTeam;
use App\Models\StaffMemberProfile;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds realistic demo data for all roles with complete relational data.
 *
 * Creates additional users per role, fixes incomplete identity data on existing
 * users, seeds teams with leaders, projects, and team member assignments.
 *
 * Safe to run multiple times (uses updateOrCreate).
 *
 * Run: php artisan db:seed --class=DemoDataSeeder
 * Or include in DatabaseSeeder after the role-specific seeders.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->fixExistingIncompleteData();
            $profiles = $this->seedAdditionalUsers();
            $teams = $this->seedTeams($profiles);
            $this->assignTeamMembers($teams, $profiles);
            $this->seedProjects($teams, $profiles);
        });

        $this->command?->info('✅ Demo data seeded successfully.');
        $this->command?->line('  New users: 5 (Rina, Fajar, Sari, Budi, Dina)');
        $this->command?->line('  Teams: 3 (Engineering, Product, Operations)');
        $this->command?->line('  Projects: 2 (HRIS Platform, Mobile App)');
    }

    // ── Fix existing incomplete data ─────────────────────────────────

    private function fixExistingIncompleteData(): void
    {
        // HR (Tasyia) — missing identity fields
        $tasyia = StaffMemberProfile::where('code', 'HR001')->first();
        if ($tasyia && ! $tasyia->npwp) {
            $tasyia->update([
                'npwp' => '03.456.789.0-123.000',
                'bpjs_ketenagakerjaan' => '22334455667',
                'bpjs_kesehatan' => '2233445566778',
                'ptkp_status' => 'TK/0',
                'religion' => 'islam',
                'marital_status' => 'single',
                'blood_type' => 'B',
            ]);
            $this->command?->line('  Fixed: Tasyia (HR) identity data');
        }

        // Finance (Dwimeta) — missing identity fields
        $dwimeta = StaffMemberProfile::where('code', 'FIN001')->first();
        if ($dwimeta && ! $dwimeta->npwp) {
            $dwimeta->update([
                'npwp' => '04.567.890.1-234.000',
                'bpjs_ketenagakerjaan' => '33445566778',
                'bpjs_kesehatan' => '3344556677889',
                'ptkp_status' => 'K/0',
                'religion' => 'kristen',
                'marital_status' => 'married',
                'blood_type' => 'AB',
            ]);
            $this->command?->line('  Fixed: Dwimeta (Finance) identity data');
        }
    }

    // ── Seed additional users ────────────────────────────────────────

    /**
     * @return array<string, StaffMemberProfile>
     */
    private function seedAdditionalUsers(): array
    {
        $blueprints = [
            // Manager #2
            [
                'email' => 'rina@teamsync.com',
                'name' => 'Rina Wulandari',
                'photo' => 'profile-pictures/female/2.avif',
                'role' => 'manager',
                'code' => 'MGR002',
                'identity_number' => '444545252',
                'npwp' => '05.678.901.2-345.000',
                'bpjs_ketenagakerjaan' => '44556677889',
                'bpjs_kesehatan' => '4455667788990',
                'ptkp_status' => 'K/1',
                'phone' => '081234567801',
                'date_of_birth' => '1992-03-15',
                'gender' => 'female',
                'religion' => 'islam',
                'marital_status' => 'married',
                'blood_type' => 'A',
                'place_of_birth' => 'Bandung',
                'address' => 'Jl. Dago No. 10',
                'city' => 'Bandung',
                'postal_code' => '40135',
                'job_title' => 'Product Manager',
                'employment_type' => 'full_time',
                'work_location' => 'hybrid',
                'monthly_salary' => 14000000,
                'bank_name' => 'mandiri',
                'account_number' => '1300012345',
                'emergency_name' => 'Ahmad Wulandari',
                'emergency_phone' => '081234567802',
                'emergency_email' => 'rina.emergency@teamsync.com',
            ],
            // HR #2
            [
                'email' => 'fajar@teamsync.com',
                'name' => 'Fajar Pratama',
                'photo' => 'profile-pictures/male/4.avif',
                'role' => 'hr',
                'code' => 'HR002',
                'identity_number' => '555656363',
                'npwp' => '06.789.012.3-456.000',
                'bpjs_ketenagakerjaan' => '55667788990',
                'bpjs_kesehatan' => '5566778899001',
                'ptkp_status' => 'TK/0',
                'phone' => '081234567803',
                'date_of_birth' => '1994-07-22',
                'gender' => 'male',
                'religion' => 'islam',
                'marital_status' => 'single',
                'blood_type' => 'O',
                'place_of_birth' => 'Surabaya',
                'address' => 'Jl. Pemuda No. 8',
                'city' => 'Surabaya',
                'postal_code' => '60271',
                'job_title' => 'HR Coordinator',
                'employment_type' => 'full_time',
                'work_location' => 'office',
                'monthly_salary' => 9000000,
                'bank_name' => 'bni',
                'account_number' => '0255012345',
                'emergency_name' => 'Siti Pratama',
                'emergency_phone' => '081234567804',
                'emergency_email' => 'fajar.emergency@teamsync.com',
            ],
            // Finance #2
            [
                'email' => 'sari@teamsync.com',
                'name' => 'Sari Dewi',
                'photo' => 'profile-pictures/female/3.avif',
                'role' => 'finance',
                'code' => 'FIN002',
                'identity_number' => '666767474',
                'npwp' => '07.890.123.4-567.000',
                'bpjs_ketenagakerjaan' => '66778899001',
                'bpjs_kesehatan' => '6677889900112',
                'ptkp_status' => 'TK/0',
                'phone' => '081234567805',
                'date_of_birth' => '1996-11-08',
                'gender' => 'female',
                'religion' => 'katolik',
                'marital_status' => 'single',
                'blood_type' => 'B',
                'place_of_birth' => 'Yogyakarta',
                'address' => 'Jl. Malioboro No. 3',
                'city' => 'Yogyakarta',
                'postal_code' => '55271',
                'job_title' => 'Finance Analyst',
                'employment_type' => 'full_time',
                'work_location' => 'remote',
                'monthly_salary' => 11000000,
                'bank_name' => 'bca',
                'account_number' => '7788012345',
                'emergency_name' => 'Dewi Lestari',
                'emergency_phone' => '081234567806',
                'emergency_email' => 'sari.emergency@teamsync.com',
            ],
            // Staff #2
            [
                'email' => 'budi@teamsync.com',
                'name' => 'Budi Santoso',
                'photo' => 'profile-pictures/male/5.avif',
                'role' => 'staff',
                'code' => 'EMP002',
                'identity_number' => '777878585',
                'npwp' => '08.901.234.5-678.000',
                'bpjs_ketenagakerjaan' => '77889900112',
                'bpjs_kesehatan' => '7788990011223',
                'ptkp_status' => 'K/0',
                'phone' => '081234567807',
                'date_of_birth' => '1997-02-14',
                'gender' => 'male',
                'religion' => 'islam',
                'marital_status' => 'married',
                'blood_type' => 'AB',
                'place_of_birth' => 'Semarang',
                'address' => 'Jl. Pandanaran No. 12',
                'city' => 'Semarang',
                'postal_code' => '50134',
                'job_title' => 'Backend Developer',
                'employment_type' => 'full_time',
                'work_location' => 'office',
                'monthly_salary' => 9500000,
                'bank_name' => 'bri',
                'account_number' => '0033012345',
                'emergency_name' => 'Santoso Family',
                'emergency_phone' => '081234567808',
                'emergency_email' => 'budi.emergency@teamsync.com',
            ],
            // Staff #3
            [
                'email' => 'dina@teamsync.com',
                'name' => 'Dina Maharani',
                'photo' => 'profile-pictures/female/4.avif',
                'role' => 'staff',
                'code' => 'EMP003',
                'identity_number' => '888989696',
                'npwp' => '09.012.345.6-789.000',
                'bpjs_ketenagakerjaan' => '88990011223',
                'bpjs_kesehatan' => '8899001122334',
                'ptkp_status' => 'TK/0',
                'phone' => '081234567809',
                'date_of_birth' => '1998-09-30',
                'gender' => 'female',
                'religion' => 'hindu',
                'marital_status' => 'single',
                'blood_type' => 'O',
                'place_of_birth' => 'Denpasar',
                'address' => 'Jl. Sunset Road No. 7',
                'city' => 'Denpasar',
                'postal_code' => '80361',
                'job_title' => 'UI/UX Designer',
                'employment_type' => 'full_time',
                'work_location' => 'hybrid',
                'monthly_salary' => 8500000,
                'bank_name' => 'mandiri',
                'account_number' => '1400012345',
                'emergency_name' => 'Maharani Family',
                'emergency_phone' => '081234567810',
                'emergency_email' => 'dina.emergency@teamsync.com',
            ],
        ];

        $profiles = [];

        foreach ($blueprints as $bp) {
            $user = User::withTrashed()->updateOrCreate(
                ['email' => $bp['email']],
                [
                    'name' => $bp['name'],
                    'password' => bcrypt('teamsync'),
                    'profile_photo' => $bp['photo'],
                    'deleted_at' => null,
                ]
            );

            $profile = StaffMemberProfile::withTrashed()->updateOrCreate(
                ['code' => $bp['code']],
                [
                    'user_id' => $user->id,
                    'identity_number' => $bp['identity_number'],
                    'npwp' => $bp['npwp'],
                    'bpjs_ketenagakerjaan' => $bp['bpjs_ketenagakerjaan'],
                    'bpjs_kesehatan' => $bp['bpjs_kesehatan'],
                    'ptkp_status' => $bp['ptkp_status'],
                    'phone' => $bp['phone'],
                    'date_of_birth' => $bp['date_of_birth'],
                    'gender' => $bp['gender'],
                    'religion' => $bp['religion'],
                    'marital_status' => $bp['marital_status'],
                    'blood_type' => $bp['blood_type'],
                    'place_of_birth' => $bp['place_of_birth'],
                    'address' => $bp['address'],
                    'city' => $bp['city'],
                    'postal_code' => $bp['postal_code'],
                    'deleted_at' => null,
                ]
            );

            $profile->jobInformation()->updateOrCreate(
                ['staff_member_id' => $profile->id],
                [
                    'job_title' => $bp['job_title'],
                    'status' => 'active',
                    'employment_type' => $bp['employment_type'],
                    'work_location' => $bp['work_location'],
                    'start_date' => '2024-06-01',
                    'monthly_salary' => $bp['monthly_salary'],
                ]
            );

            $profile->bankInformation()->updateOrCreate(
                ['staff_member_id' => $profile->id],
                [
                    'bank_name' => $bp['bank_name'],
                    'account_number' => $bp['account_number'],
                    'account_holder_name' => $bp['name'],
                ]
            );

            $profile->emergencyContacts()->updateOrCreate(
                ['staff_member_id' => $profile->id, 'email' => $bp['emergency_email']],
                [
                    'full_name' => $bp['emergency_name'],
                    'phone' => $bp['emergency_phone'],
                    'relationship' => 'Family',
                    'email' => $bp['emergency_email'],
                ]
            );

            $user->syncRoles([$bp['role']]);
            $profiles[$bp['code']] = $profile;

            $this->command?->line("  Seeded: {$bp['name']} ({$bp['role']})");
        }

        return $profiles;
    }

    // ── Seed teams ───────────────────────────────────────────────────

    /**
     * @param  array<string, StaffMemberProfile>  $newProfiles
     * @return array<string, Team>
     */
    private function seedTeams(array $newProfiles): array
    {
        // Get existing user IDs for team leads
        $yudhis = User::where('email', 'yudhis@teamsync.com')->first();
        $rina = User::where('email', 'rina@teamsync.com')->first();
        $tasyia = User::where('email', 'tasyia@teamsync.com')->first();

        $teams = [];

        // Engineering team — led by Yudhis (Manager)
        $teams['engineering'] = Team::withTrashed()->updateOrCreate(
            ['name' => 'Engineering'],
            [
                'expected_size' => 5,
                'description' => 'Core engineering team responsible for backend, frontend, and infrastructure development.',
                'icon' => 'team-icons/activity.png',
                'department' => Department::DEVELOPMENT->value,
                'status' => TeamStatus::ACTIVE->value,
                'team_lead_id' => $yudhis?->id,
                'responsibilities' => [
                    'Build and maintain core platform',
                    'Code review and quality assurance',
                    'Technical architecture decisions',
                    'CI/CD pipeline management',
                ],
                'deleted_at' => null,
            ]
        );

        // Product team — led by Rina (Manager #2)
        $teams['product'] = Team::withTrashed()->updateOrCreate(
            ['name' => 'Product & Design'],
            [
                'expected_size' => 4,
                'description' => 'Product management and design team driving user experience and feature roadmap.',
                'icon' => 'team-icons/pen-tool.png',
                'department' => Department::DESIGN->value,
                'status' => TeamStatus::ACTIVE->value,
                'team_lead_id' => $rina?->id,
                'responsibilities' => [
                    'Define product roadmap',
                    'User research and testing',
                    'UI/UX design and prototyping',
                    'Feature prioritization',
                ],
                'deleted_at' => null,
            ]
        );

        // Operations team — led by Tasyia (HR)
        $teams['operations'] = Team::withTrashed()->updateOrCreate(
            ['name' => 'Operations'],
            [
                'expected_size' => 4,
                'description' => 'HR, finance, and administrative operations ensuring smooth company processes.',
                'icon' => 'team-icons/coffee.png',
                'department' => Department::MANAGEMENT->value,
                'status' => TeamStatus::ACTIVE->value,
                'team_lead_id' => $tasyia?->id,
                'responsibilities' => [
                    'Payroll processing and compliance',
                    'Employee onboarding and offboarding',
                    'Financial reporting and budgeting',
                    'Policy development and enforcement',
                ],
                'deleted_at' => null,
            ]
        );

        return $teams;
    }

    // ── Assign team members ──────────────────────────────────────────

    /**
     * @param  array<string, Team>  $teams
     * @param  array<string, StaffMemberProfile>  $newProfiles
     */
    private function assignTeamMembers(array $teams, array $newProfiles): void
    {
        // Map: team key => [staff_member codes]
        $assignments = [
            'engineering' => [
                'MGR001', // Yudhis (Manager, team lead)
                'EMP001', // Agung (Staff, Software Engineer)
                'EMP002', // Budi (Staff, Backend Developer)
            ],
            'product' => [
                'MGR002', // Rina (Manager, team lead)
                'EMP003', // Dina (Staff, UI/UX Designer)
            ],
            'operations' => [
                'HR001',  // Tasyia (HR, team lead)
                'HR002',  // Fajar (HR Coordinator)
                'FIN001', // Dwimeta (Finance Manager)
                'FIN002', // Sari (Finance Analyst)
            ],
        ];

        foreach ($assignments as $teamKey => $codes) {
            $team = $teams[$teamKey];

            foreach ($codes as $code) {
                $profile = StaffMemberProfile::where('code', $code)->first();
                if (! $profile) {
                    continue;
                }

                TeamMember::withTrashed()->updateOrCreate(
                    [
                        'team_id' => $team->id,
                        'staff_member_id' => $profile->id,
                    ],
                    [
                        'joined_at' => '2024-06-01',
                        'left_at' => null,
                        'deleted_at' => null,
                    ]
                );
            }

            $this->command?->line("  Team '{$team->name}': ".count($codes).' members assigned');
        }
    }

    // ── Seed projects ────────────────────────────────────────────────

    /**
     * @param  array<string, Team>  $teams
     * @param  array<string, StaffMemberProfile>  $newProfiles
     */
    private function seedProjects(array $teams, array $newProfiles): void
    {
        // Get manager profile for project leader
        $yudhisProfile = StaffMemberProfile::where('code', 'MGR001')->first();
        $rinaProfile = StaffMemberProfile::where('code', 'MGR002')->first();

        // Project 1: HRIS Platform (Engineering + Operations)
        $hris = Project::withTrashed()->updateOrCreate(
            ['name' => 'Team Sync HRIS Platform'],
            [
                'type' => ProjectType::WEB_DEVELOPMENT->value,
                'priority' => ProjectPriority::HIGH->value,
                'status' => ProjectStatus::ACTIVE->value,
                'start_date' => '2024-06-01',
                'end_date' => '2026-12-31',
                'description' => 'Full-featured HRIS platform with payroll, attendance, performance management, and employee self-service.',
                'budget' => 500000000,
                'project_leader_id' => $yudhisProfile?->id,
                'deleted_at' => null,
            ]
        );

        // Attach teams to HRIS project
        foreach (['engineering', 'operations'] as $teamKey) {
            ProjectTeam::withTrashed()->updateOrCreate(
                [
                    'project_id' => $hris->id,
                    'team_id' => $teams[$teamKey]->id,
                ],
                [
                    'assigned_at' => '2024-06-01',
                    'deleted_at' => null,
                ]
            );
        }

        $this->command?->line("  Project '{$hris->name}': 2 teams attached");

        // Project 2: Mobile App (Product + Engineering)
        $mobile = Project::withTrashed()->updateOrCreate(
            ['name' => 'Team Sync Mobile App'],
            [
                'type' => ProjectType::MOBILE_APP->value,
                'priority' => ProjectPriority::MEDIUM->value,
                'status' => ProjectStatus::PLANNING->value,
                'start_date' => '2026-07-01',
                'end_date' => '2027-06-30',
                'description' => 'Mobile companion app for Team Sync — clock in/out, leave requests, payslip viewing, and push notifications.',
                'budget' => 300000000,
                'project_leader_id' => $rinaProfile?->id,
                'deleted_at' => null,
            ]
        );

        // Attach teams to Mobile project
        foreach (['product', 'engineering'] as $teamKey) {
            ProjectTeam::withTrashed()->updateOrCreate(
                [
                    'project_id' => $mobile->id,
                    'team_id' => $teams[$teamKey]->id,
                ],
                [
                    'assigned_at' => '2026-07-01',
                    'deleted_at' => null,
                ]
            );
        }

        $this->command?->line("  Project '{$mobile->name}': 2 teams attached");
    }
}
