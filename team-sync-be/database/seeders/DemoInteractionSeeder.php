<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\PayrollSetting;
use App\Models\PerformanceFeedback;
use App\Models\PerformanceGoal;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds interaction data: attendance, payroll settings, goals, feedback, tasks, leave requests.
 *
 * Run AFTER DemoDataSeeder. Safe to run multiple times (uses updateOrCreate where possible).
 *
 * Run: php artisan db:seed --class=DemoInteractionSeeder
 */
class DemoInteractionSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPayrollSettings();
        $this->seedAttendanceForCurrentMonth();
        $this->seedPerformanceGoals();
        $this->seedPerformanceFeedback();
        $this->seedProjectTasks();
        $this->seedLeaveRequests();

        $this->command?->info('✅ Demo interaction data seeded successfully.');
    }

    // ── Payroll Settings ─────────────────────────────────────────────

    private function seedPayrollSettings(): void
    {
        PayrollSetting::updateOrCreate(
            ['id' => 1],
            [
                'payday_day' => 25,
                'attendance_cutoff_day' => 25,
                'working_days_mode' => 'auto_business_days',
                'default_working_days' => 22,
                'absent_deduction_rate' => 1.00,
                'rounding_mode' => 'nearest',
                'rounding_unit' => 1000,
                'note_template' => 'Payroll for {month} {year}',
            ]
        );

        $this->command?->line('  Payroll settings configured');
    }

    // ── Attendance ───────────────────────────────────────────────────

    private function seedAttendanceForCurrentMonth(): void
    {
        $employees = StaffMemberProfile::whereHas('jobInformation', fn ($q) => $q->where('status', 'active'))->get();
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $yesterday = $now->copy()->subDay();

        // Only seed past work days (not today or future)
        $workDays = [];
        $current = $startOfMonth->copy();
        while ($current->lte($yesterday)) {
            if ($current->isWeekday()) {
                $workDays[] = $current->format('Y-m-d');
            }
            $current->addDay();
        }

        if (empty($workDays)) {
            $this->command?->line('  No past work days this month — skipping attendance');
            return;
        }

        $batch = [];
        $count = 0;

        foreach ($employees as $employee) {
            foreach ($workDays as $date) {
                // Skip if already exists
                $exists = Attendance::where('staff_member_id', $employee->id)
                    ->whereDate('date', $date)
                    ->exists();
                if ($exists) continue;

                // Realistic distribution: 75% present, 10% late, 5% sick, 5% absent, 5% half_day
                $rand = rand(1, 100);
                if ($rand <= 75) {
                    $status = 'present';
                } elseif ($rand <= 85) {
                    $status = 'late';
                } elseif ($rand <= 90) {
                    $status = 'sick';
                } elseif ($rand <= 95) {
                    $status = 'absent';
                } else {
                    $status = 'half_day';
                }

                $checkIn = null;
                $checkOut = null;
                $workedMinutes = null;

                if (in_array($status, ['present', 'late', 'half_day'])) {
                    $hour = $status === 'late' ? rand(9, 10) : 8;
                    $minute = $status === 'late' ? rand(31, 59) : rand(0, 15);
                    $checkIn = "{$date} " . sprintf('%02d:%02d:00', $hour, $minute);

                    if ($status === 'half_day') {
                        $checkOut = "{$date} " . sprintf('%02d:%02d:00', 13, rand(0, 30));
                        $workedMinutes = rand(240, 270); // ~4-4.5h
                    } else {
                        $checkOut = "{$date} " . sprintf('%02d:%02d:00', 17, rand(0, 45));
                        $workedMinutes = rand(420, 540); // ~7-9h
                    }
                }

                $batch[] = [
                    'staff_member_id' => $employee->id,
                    'date' => $date,
                    'status' => $status,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'worked_minutes' => $workedMinutes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($batch) >= 500) {
                    DB::table('attendances')->insert($batch);
                    $count += count($batch);
                    $batch = [];
                }
            }
        }

        if (!empty($batch)) {
            DB::table('attendances')->insert($batch);
            $count += count($batch);
        }

        $this->command?->line("  Attendance: {$count} records for " . count($workDays) . " work days");
    }

    // ── Performance Goals ────────────────────────────────────────────

    private function seedPerformanceGoals(): void
    {
        $agung = StaffMemberProfile::where('code', 'EMP001')->first();
        $budi = StaffMemberProfile::where('code', 'EMP002')->first();
        $dina = StaffMemberProfile::where('code', 'EMP003')->first();
        $yudhis = User::where('email', 'yudhis@teamsync.com')->first();
        $rina = User::where('email', 'rina@teamsync.com')->first();

        if (!$agung || !$budi || !$dina || !$yudhis) return;

        $goals = [
            [
                'staff_member_id' => $agung->id,
                'title' => 'Complete Laravel Testing Course',
                'description' => 'Finish the Pest PHP testing course and apply patterns to Team Sync codebase.',
                'goal_type' => 'development',
                'status' => 'in_progress',
                'completion_percentage' => 60,
                'start_date' => '2026-01-15',
                'due_date' => '2026-04-30',
                'created_by' => $yudhis->id,
                'assigned_by' => $yudhis->staffMemberProfile?->id,
            ],
            [
                'staff_member_id' => $agung->id,
                'title' => 'Reduce API Response Time by 20%',
                'description' => 'Optimize slow endpoints identified in performance audit.',
                'goal_type' => 'kpi',
                'target_value' => '200ms',
                'current_value' => '250ms',
                'unit' => 'ms',
                'status' => 'in_progress',
                'completion_percentage' => 40,
                'start_date' => '2026-02-01',
                'due_date' => '2026-05-31',
                'created_by' => $yudhis->id,
                'assigned_by' => $yudhis->staffMemberProfile?->id,
            ],
            [
                'staff_member_id' => $budi->id,
                'title' => 'Implement CI/CD Pipeline',
                'description' => 'Set up automated testing and deployment pipeline for Team Sync.',
                'goal_type' => 'project',
                'status' => 'not_started',
                'completion_percentage' => 0,
                'start_date' => '2026-04-01',
                'due_date' => '2026-06-30',
                'created_by' => $yudhis->id,
                'assigned_by' => $yudhis->staffMemberProfile?->id,
            ],
            [
                'staff_member_id' => $dina->id,
                'title' => 'Redesign Dashboard UI',
                'description' => 'Create a modern, data-rich dashboard design for all roles.',
                'goal_type' => 'project',
                'status' => 'in_progress',
                'completion_percentage' => 75,
                'start_date' => '2026-02-01',
                'due_date' => '2026-04-30',
                'created_by' => $rina?->id ?? $yudhis->id,
                'assigned_by' => $rina?->staffMemberProfile?->id,
            ],
            [
                'staff_member_id' => $agung->id,
                'title' => 'Write API Documentation',
                'description' => 'Document all REST API endpoints with examples.',
                'goal_type' => 'kpi',
                'status' => 'completed',
                'completion_percentage' => 100,
                'completed_at' => '2026-03-20',
                'start_date' => '2026-01-01',
                'due_date' => '2026-03-31',
                'created_by' => $agung->user_id,
                'assigned_by' => $agung->id,
            ],
        ];

        foreach ($goals as $goal) {
            PerformanceGoal::updateOrCreate(
                ['staff_member_id' => $goal['staff_member_id'], 'title' => $goal['title']],
                $goal
            );
        }

        $this->command?->line('  Performance goals: ' . count($goals) . ' seeded');
    }

    // ── Performance Feedback ─────────────────────────────────────────

    private function seedPerformanceFeedback(): void
    {
        $agung = StaffMemberProfile::where('code', 'EMP001')->first();
        $budi = StaffMemberProfile::where('code', 'EMP002')->first();
        $dina = StaffMemberProfile::where('code', 'EMP003')->first();
        $yudhisProfile = StaffMemberProfile::where('code', 'MGR001')->first();
        $rinaProfile = StaffMemberProfile::where('code', 'MGR002')->first();

        if (!$agung || !$budi || !$yudhisProfile) return;

        $feedbacks = [
            [
                'staff_member_id' => $agung->id,
                'given_by' => $yudhisProfile->id,
                'feedback_type' => 'positive',
                'category' => 'technical',
                'content' => 'Excellent work on the payroll module. The error handling standardization was thorough and well-tested.',
            ],
            [
                'staff_member_id' => $agung->id,
                'given_by' => $budi->id,
                'feedback_type' => 'positive',
                'category' => 'collaboration',
                'content' => 'Great pair programming session on the attendance feature. Learned a lot about Eloquent query optimization.',
            ],
            [
                'staff_member_id' => $budi->id,
                'given_by' => $yudhisProfile->id,
                'feedback_type' => 'constructive',
                'category' => 'technical',
                'content' => 'Consider writing more comprehensive test cases. The CI/CD pipeline setup needs better error handling for edge cases.',
            ],
            [
                'staff_member_id' => $budi->id,
                'given_by' => $agung->id,
                'feedback_type' => 'positive',
                'category' => 'collaboration',
                'content' => 'Always willing to help with code reviews. Provides clear and actionable feedback.',
            ],
            [
                'staff_member_id' => $dina->id,
                'given_by' => $rinaProfile?->id ?? $yudhisProfile->id,
                'feedback_type' => 'positive',
                'category' => 'design',
                'content' => 'The new dashboard design is beautiful and intuitive. Users love the new stats cards.',
            ],
            [
                'staff_member_id' => $yudhisProfile->id,
                'given_by' => $agung->id,
                'feedback_type' => 'positive',
                'category' => 'leadership',
                'content' => 'Clear technical direction and always available for architecture discussions.',
            ],
        ];

        foreach ($feedbacks as $fb) {
            PerformanceFeedback::updateOrCreate(
                ['staff_member_id' => $fb['staff_member_id'], 'given_by' => $fb['given_by'], 'content' => $fb['content']],
                $fb
            );
        }

        $this->command?->line('  Performance feedback: ' . count($feedbacks) . ' seeded');
    }

    // ── Project Tasks ────────────────────────────────────────────────

    private function seedProjectTasks(): void
    {
        $hris = Project::where('name', 'Team Sync HRIS Platform')->first();
        $mobile = Project::where('name', 'Team Sync Mobile App')->first();

        $agung = StaffMemberProfile::where('code', 'EMP001')->first();
        $budi = StaffMemberProfile::where('code', 'EMP002')->first();
        $dina = StaffMemberProfile::where('code', 'EMP003')->first();

        if (!$hris || !$agung) return;

        $tasks = [
            // HRIS project tasks
            [
                'project_id' => $hris->id,
                'name' => 'Implement data export to CSV',
                'description' => 'Add CSV export functionality for attendance and payroll reports.',
                'assignee_id' => $agung->id,
                'priority' => 'high',
                'status' => 'in_progress',
                'due_date' => Carbon::now()->addDays(5)->format('Y-m-d'),
            ],
            [
                'project_id' => $hris->id,
                'name' => 'Fix mobile responsive layout',
                'description' => 'Dashboard cards overlap on mobile screens below 375px.',
                'assignee_id' => $dina?->id,
                'priority' => 'medium',
                'status' => 'todo',
                'due_date' => Carbon::now()->addDays(7)->format('Y-m-d'),
            ],
            [
                'project_id' => $hris->id,
                'name' => 'Add unit tests for PayrollRepository',
                'description' => 'Increase test coverage for payroll calculation edge cases.',
                'assignee_id' => $budi?->id,
                'priority' => 'medium',
                'status' => 'todo',
                'due_date' => Carbon::now()->addDays(10)->format('Y-m-d'),
            ],
            [
                'project_id' => $hris->id,
                'name' => 'Optimize attendance query performance',
                'description' => 'Attendance list endpoint is slow with 500+ employees.',
                'assignee_id' => $agung->id,
                'priority' => 'high',
                'status' => 'review',
                'due_date' => Carbon::now()->addDays(2)->format('Y-m-d'),
            ],
            [
                'project_id' => $hris->id,
                'name' => 'Setup Sentry error tracking',
                'description' => 'Integrate Sentry for production error monitoring.',
                'assignee_id' => $budi?->id,
                'priority' => 'low',
                'status' => 'todo',
                'due_date' => Carbon::now()->addDays(14)->format('Y-m-d'),
            ],
        ];

        // Mobile project tasks
        if ($mobile) {
            $tasks[] = [
                'project_id' => $mobile->id,
                'name' => 'Design mobile login screen',
                'description' => 'Create Figma mockup for the mobile login flow.',
                'assignee_id' => $dina?->id,
                'priority' => 'high',
                'status' => 'in_progress',
                'due_date' => Carbon::now()->addDays(3)->format('Y-m-d'),
            ];
            $tasks[] = [
                'project_id' => $mobile->id,
                'name' => 'Research push notification providers',
                'description' => 'Compare Firebase, OneSignal, and Pusher for mobile push notifications.',
                'assignee_id' => $agung->id,
                'priority' => 'medium',
                'status' => 'todo',
                'due_date' => Carbon::now()->addDays(12)->format('Y-m-d'),
            ];
        }

        foreach ($tasks as $task) {
            ProjectTask::updateOrCreate(
                ['project_id' => $task['project_id'], 'name' => $task['name']],
                $task
            );
        }

        $this->command?->line('  Project tasks: ' . count($tasks) . ' seeded');
    }

    // ── Leave Requests ───────────────────────────────────────────────

    private function seedLeaveRequests(): void
    {
        $agung = StaffMemberProfile::where('code', 'EMP001')->first();
        $budi = StaffMemberProfile::where('code', 'EMP002')->first();
        $tasyia = User::where('email', 'tasyia@teamsync.com')->first();

        if (!$agung || !$tasyia) return;

        $requests = [
            [
                'staff_member_id' => $agung->id,
                'leave_type' => 'sick_leave',
                'start_date' => Carbon::now()->subDays(5)->format('Y-m-d'),
                'end_date' => Carbon::now()->subDays(5)->format('Y-m-d'),
                'total_days' => 1,
                'reason' => 'Flu and fever, need rest.',
                'status' => 'approved',
                'approved_by' => $tasyia->id,
            ],
            [
                'staff_member_id' => $agung->id,
                'leave_type' => 'annual_leave',
                'start_date' => Carbon::now()->addDays(10)->format('Y-m-d'),
                'end_date' => Carbon::now()->addDays(12)->format('Y-m-d'),
                'total_days' => 3,
                'reason' => 'Family vacation.',
                'status' => 'pending',
            ],
            [
                'staff_member_id' => $budi?->id ?? $agung->id,
                'leave_type' => 'sick_leave',
                'start_date' => Carbon::now()->subDays(10)->format('Y-m-d'),
                'end_date' => Carbon::now()->subDays(9)->format('Y-m-d'),
                'total_days' => 2,
                'reason' => 'Food poisoning.',
                'status' => 'approved',
                'approved_by' => $tasyia->id,
            ],
        ];

        foreach ($requests as $req) {
            LeaveRequest::updateOrCreate(
                ['staff_member_id' => $req['staff_member_id'], 'start_date' => $req['start_date'], 'leave_type' => $req['leave_type']],
                $req
            );
        }

        $this->command?->line('  Leave requests: ' . count($requests) . ' seeded');
    }
}
