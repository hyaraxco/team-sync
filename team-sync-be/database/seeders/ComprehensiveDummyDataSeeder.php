<?php

/**
 * ComprehensiveDummyDataSeeder
 *
 * Seeds ALL modules with realistic data for all 9 employees.
 * Ensures no page in the app shows empty/NaN values.
 *
 * Modules seeded:
 * 1. Payroll records (3 months: 2026-02, 2026-03, 2026-04)
 * 2. Overtime records (spread across 5 employees)
 * 3. Meetings (4 meetings)
 * 4. Leave requests (for employees without existing ones)
 * 5. Performance goals (for employees without existing ones)
 * 6. Performance feedback (expand to all employees)
 * 7. Attendance for past 2 months (2026-03, 2026-04)
 *
 * Idempotent: uses updateOrCreate / check-before-insert patterns.
 */

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\Meeting;
use App\Models\OvertimeRecord;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PerformanceFeedback;
use App\Models\PerformanceGoal;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ComprehensiveDummyDataSeeder extends Seeder
{
    private array $employees = [
        'yudhis@teamsync.com' => ['code' => 'MGR001', 'salary' => 15000000],
        'agung@teamsync.com' => ['code' => 'EMP001', 'salary' => 10000000],
        'tasyia@teamsync.com' => ['code' => 'HR001', 'salary' => 12000000],
        'dwimeta@teamsync.com' => ['code' => 'FIN001', 'salary' => 13000000],
        'rina@teamsync.com' => ['code' => 'MGR002', 'salary' => 14000000],
        'fajar@teamsync.com' => ['code' => 'HR002', 'salary' => 9000000],
        'sari@teamsync.com' => ['code' => 'FIN002', 'salary' => 11000000],
        'budi@teamsync.com' => ['code' => 'EMP002', 'salary' => 9500000],
        'dina@teamsync.com' => ['code' => 'EMP003', 'salary' => 8500000],
    ];

    private array $profiles = [];

    private array $users = [];

    public function run(): void
    {
        $this->loadProfiles();

        if (empty($this->profiles)) {
            $this->command?->line('No employee profiles found. Skipping.');

            return;
        }

        $this->seedPayroll();
        $this->seedOvertime();
        $this->seedMeetings();
        $this->seedLeaveRequests();
        $this->seedPerformanceGoals();
        $this->seedPerformanceFeedback();
        $this->seedAttendance();

        $this->command?->line('ComprehensiveDummyDataSeeder completed.');
    }

    private function loadProfiles(): void
    {
        foreach ($this->employees as $email => $data) {
            $user = User::where('email', $email)->first();
            if (! $user) {
                $this->command?->line("User {$email} not found, skipping.");

                continue;
            }

            $profile = StaffMemberProfile::where('user_id', $user->id)->first();
            if (! $profile) {
                $this->command?->line("Profile for {$email} not found, skipping.");

                continue;
            }

            $this->users[$email] = $user;
            $this->profiles[$email] = $profile;
        }

        $this->command?->line('Loaded '.count($this->profiles).' employee profiles.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. PAYROLL (3 months: 2026-02, 2026-03, 2026-04)
    // ─────────────────────────────────────────────────────────────────────────

    private function seedPayroll(): void
    {
        $months = ['2026-02-01', '2026-03-01', '2026-04-01'];

        foreach ($months as $month) {
            $salaryMonth = Carbon::parse($month);
            $paymentDate = $salaryMonth->copy()->endOfMonth();

            $payroll = Payroll::updateOrCreate(
                ['salary_month' => $salaryMonth->format('Y-m-d')],
                [
                    'payment_date' => $paymentDate->format('Y-m-d'),
                    'status' => 'paid',
                    'processed_count' => count($this->profiles),
                    'correction_count' => 0,
                ]
            );

            foreach ($this->profiles as $email => $profile) {
                $salary = $this->employees[$email]['salary'];
                $presentDays = rand(20, 22);
                $absentDays = 22 - $presentDays;

                $bpjsJht = round($salary * 0.02);
                $bpjsJp = round(min($salary, 10042300) * 0.01);
                $bpjsKes = round(min($salary, 12000000) * 0.01);
                $pph21 = round($salary * 0.025);
                $totalDeductions = $bpjsJht + $bpjsJp + $bpjsKes + $pph21;
                $netSalary = $salary - $totalDeductions;
                $dailyRate = round($salary / 22);

                PayrollDetail::updateOrCreate(
                    [
                        'payroll_id' => $payroll->id,
                        'staff_member_id' => $profile->id,
                    ],
                    [
                        'original_salary' => $salary,
                        'final_salary' => $netSalary,
                        'effective_working_days' => 22,
                        'daily_rate' => $dailyRate,
                        'attended_days' => $presentDays,
                        'present_days' => $presentDays,
                        'late_days' => rand(0, 2),
                        'half_day_count' => 0,
                        'paid_leave_days' => 0,
                        'unpaid_leave_days' => 0,
                        'holiday_days' => 0,
                        'sick_days' => 0,
                        'absent_days' => $absentDays,
                        'deduction_days' => $absentDays,
                        'deduction_amount' => $totalDeductions,
                        'overtime_hours' => 0,
                        'overtime_amount' => 0,
                        'overtime_records_count' => 0,
                        'policy_mismatch_days' => 0,
                        'warning_flags' => null,
                        'notes' => "BPJS JHT: {$bpjsJht}, JP: {$bpjsJp}, Kes: {$bpjsKes}, PPh21: {$pph21}",
                    ]
                );
            }

            $this->command?->line("Payroll seeded for {$salaryMonth->format('Y-m')}.");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. OVERTIME RECORDS
    // ─────────────────────────────────────────────────────────────────────────

    private function seedOvertime(): void
    {
        $overtimeEmployees = ['agung@teamsync.com', 'budi@teamsync.com', 'dina@teamsync.com', 'fajar@teamsync.com', 'sari@teamsync.com'];
        $approver = $this->users['tasyia@teamsync.com'] ?? null;

        if (! $approver) {
            $this->command?->line('Approver (Tasyia) not found, skipping overtime.');

            return;
        }

        $reasons = [
            'Deadline project deployment',
            'Client meeting preparation',
            'Monthly report finalization',
            'System maintenance window',
            'Bug fix for production issue',
            'Data migration support',
            'Quarter-end reconciliation',
        ];

        $count = 0;
        foreach ($overtimeEmployees as $email) {
            if (! isset($this->profiles[$email])) {
                continue;
            }

            $profile = $this->profiles[$email];

            for ($i = 0; $i < 3; $i++) {
                $date = Carbon::create(2026, rand(3, 4), rand(1, 28));

                // Skip weekends
                if ($date->isWeekend()) {
                    $date = $date->next(Carbon::MONDAY);
                }

                $hours = rand(1, 4);
                $startTime = '17:00';
                $endTime = Carbon::parse($startTime)->addHours($hours)->format('H:i');

                OvertimeRecord::updateOrCreate(
                    [
                        'staff_member_id' => $profile->id,
                        'date' => $date->format('Y-m-d'),
                    ],
                    [
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'hours' => $hours,
                        'overtime_type' => 'workday',
                        'status' => 'approved',
                        'approved_by' => $approver->id,
                        'approved_at' => $date->copy()->addDay()->setHour(9),
                        'notes' => $reasons[array_rand($reasons)],
                    ]
                );
                $count++;
            }
        }

        $this->command?->line("Overtime seeded: {$count} records.");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. MEETINGS
    // ─────────────────────────────────────────────────────────────────────────

    private function seedMeetings(): void
    {
        $creatorTasyia = $this->users['tasyia@teamsync.com'] ?? null;
        $creatorYudhis = $this->users['yudhis@teamsync.com'] ?? null;

        $meetings = [
            [
                'title' => 'Monthly All-Hands Meeting',
                'description' => 'Company-wide update on Q2 progress, new hires, and upcoming initiatives.',
                'scheduled_at' => Carbon::create(2026, 4, 7, 10, 0, 0),
                'duration_minutes' => 60,
                'location' => 'https://meet.google.com/abc-defg-hij',
                'created_by' => $creatorYudhis?->id,
            ],
            [
                'title' => 'HR Policy Review',
                'description' => 'Review updated leave policies and BPJS rate changes for 2026.',
                'scheduled_at' => Carbon::create(2026, 4, 14, 14, 0, 0),
                'duration_minutes' => 45,
                'location' => 'Meeting Room A - Lantai 3',
                'created_by' => $creatorTasyia?->id,
            ],
            [
                'title' => 'Sprint Planning - Team Alpha',
                'description' => 'Plan sprint backlog for the next 2-week cycle. Bring updated task estimates.',
                'scheduled_at' => Carbon::create(2026, 5, 26, 9, 30, 0),
                'duration_minutes' => 30,
                'location' => 'https://meet.google.com/klm-nopq-rst',
                'created_by' => $creatorYudhis?->id,
            ],
            [
                'title' => 'Performance Calibration Session',
                'description' => 'Cross-team calibration for Q1 2026 performance reviews.',
                'scheduled_at' => Carbon::create(2026, 6, 2, 13, 0, 0),
                'duration_minutes' => 60,
                'location' => 'https://meet.google.com/uvw-xyza-bcd',
                'created_by' => $creatorTasyia?->id,
            ],
        ];

        foreach ($meetings as $meetingData) {
            if (! $meetingData['created_by']) {
                continue;
            }

            Meeting::updateOrCreate(
                ['title' => $meetingData['title'], 'scheduled_at' => $meetingData['scheduled_at']],
                $meetingData
            );
        }

        $this->command?->line('Meetings seeded: '.count($meetings).' records.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. LEAVE REQUESTS
    // ─────────────────────────────────────────────────────────────────────────

    private function seedLeaveRequests(): void
    {
        $leaveData = [
            'yudhis@teamsync.com' => [
                ['leave_type' => 'annual_leave', 'start' => '2026-03-10', 'end' => '2026-03-12', 'days' => 3, 'status' => 'approved', 'reason' => 'Family vacation to Bali'],
            ],
            'tasyia@teamsync.com' => [
                ['leave_type' => 'sick_leave', 'start' => '2026-04-03', 'end' => '2026-04-04', 'days' => 2, 'status' => 'approved', 'reason' => 'Flu and fever'],
            ],
            'dwimeta@teamsync.com' => [
                ['leave_type' => 'personal_leave', 'start' => '2026-03-20', 'end' => '2026-03-20', 'days' => 1, 'status' => 'approved', 'reason' => 'Family event attendance'],
            ],
            'rina@teamsync.com' => [
                ['leave_type' => 'annual_leave', 'start' => '2026-04-21', 'end' => '2026-04-25', 'days' => 5, 'status' => 'pending', 'reason' => 'Planned holiday trip'],
            ],
            'fajar@teamsync.com' => [
                ['leave_type' => 'sick_leave', 'start' => '2026-03-05', 'end' => '2026-03-05', 'days' => 1, 'status' => 'approved', 'reason' => 'Dental appointment'],
                ['leave_type' => 'annual_leave', 'start' => '2026-04-14', 'end' => '2026-04-16', 'days' => 3, 'status' => 'rejected', 'reason' => 'Short trip to Yogyakarta'],
            ],
            'sari@teamsync.com' => [
                ['leave_type' => 'personal_leave', 'start' => '2026-04-07', 'end' => '2026-04-08', 'days' => 2, 'status' => 'approved', 'reason' => 'Moving to new apartment'],
            ],
            'dina@teamsync.com' => [
                ['leave_type' => 'annual_leave', 'start' => '2026-03-24', 'end' => '2026-03-26', 'days' => 3, 'status' => 'approved', 'reason' => 'Attending wedding in Surabaya'],
            ],
        ];

        $approverProfile = $this->profiles['tasyia@teamsync.com'] ?? null;
        $count = 0;

        foreach ($leaveData as $email => $leaves) {
            if (! isset($this->profiles[$email])) {
                continue;
            }

            $profile = $this->profiles[$email];

            foreach ($leaves as $leave) {
                $existing = LeaveRequest::where('staff_member_id', $profile->id)
                    ->where('start_date', $leave['start'])
                    ->where('end_date', $leave['end'])
                    ->exists();

                if ($existing) {
                    continue;
                }

                LeaveRequest::create([
                    'staff_member_id' => $profile->id,
                    'leave_type' => $leave['leave_type'],
                    'start_date' => $leave['start'],
                    'end_date' => $leave['end'],
                    'total_days' => $leave['days'],
                    'reason' => $leave['reason'],
                    'status' => $leave['status'],
                    'approved_by' => $leave['status'] === 'approved' ? $approverProfile?->id : null,
                ]);
                $count++;
            }
        }

        $this->command?->line("Leave requests seeded: {$count} records.");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. PERFORMANCE GOALS
    // ─────────────────────────────────────────────────────────────────────────

    private function seedPerformanceGoals(): void
    {
        $goalsData = [
            'yudhis@teamsync.com' => [
                ['title' => 'Improve team delivery velocity by 20%', 'type' => 'okr', 'status' => 'in_progress', 'completion' => 60, 'category' => 'leadership'],
                ['title' => 'Complete leadership training program', 'type' => 'development', 'status' => 'completed', 'completion' => 100, 'category' => 'professional_development'],
                ['title' => 'Reduce sprint spillover to under 10%', 'type' => 'kpi', 'status' => 'in_progress', 'completion' => 45, 'category' => 'delivery'],
            ],
            'tasyia@teamsync.com' => [
                ['title' => 'Implement new onboarding workflow', 'type' => 'project', 'status' => 'in_progress', 'completion' => 70, 'category' => 'process_improvement'],
                ['title' => 'Achieve 95% employee satisfaction score', 'type' => 'kpi', 'status' => 'in_progress', 'completion' => 80, 'category' => 'engagement'],
            ],
            'dwimeta@teamsync.com' => [
                ['title' => 'Automate monthly payroll reconciliation', 'type' => 'project', 'status' => 'completed', 'completion' => 100, 'category' => 'automation'],
                ['title' => 'Reduce payroll processing time by 30%', 'type' => 'okr', 'status' => 'in_progress', 'completion' => 55, 'category' => 'efficiency'],
            ],
            'rina@teamsync.com' => [
                ['title' => 'Launch product feature X by Q2', 'type' => 'project', 'status' => 'in_progress', 'completion' => 40, 'category' => 'delivery'],
                ['title' => 'Mentor 2 junior developers', 'type' => 'development', 'status' => 'not_started', 'completion' => 0, 'category' => 'mentoring'],
                ['title' => 'Achieve zero critical bugs in production', 'type' => 'kpi', 'status' => 'in_progress', 'completion' => 85, 'category' => 'quality'],
            ],
            'fajar@teamsync.com' => [
                ['title' => 'Digitize employee records for 100% compliance', 'type' => 'project', 'status' => 'in_progress', 'completion' => 65, 'category' => 'compliance'],
                ['title' => 'Conduct quarterly HR audits', 'type' => 'kpi', 'status' => 'in_progress', 'completion' => 50, 'category' => 'governance'],
            ],
            'sari@teamsync.com' => [
                ['title' => 'Implement budget tracking dashboard', 'type' => 'project', 'status' => 'not_started', 'completion' => 0, 'category' => 'tooling'],
                ['title' => 'Reduce invoice processing errors to zero', 'type' => 'kpi', 'status' => 'in_progress', 'completion' => 75, 'category' => 'accuracy'],
            ],
        ];

        $count = 0;
        foreach ($goalsData as $email => $goals) {
            if (! isset($this->profiles[$email])) {
                continue;
            }

            $profile = $this->profiles[$email];
            $user = $this->users[$email];

            foreach ($goals as $goal) {
                $existing = PerformanceGoal::where('staff_member_id', $profile->id)
                    ->where('title', $goal['title'])
                    ->exists();

                if ($existing) {
                    continue;
                }

                PerformanceGoal::create([
                    'staff_member_id' => $profile->id,
                    'title' => $goal['title'],
                    'description' => 'Goal set for Q1-Q2 2026 performance cycle.',
                    'goal_type' => $goal['type'],
                    'category' => $goal['category'],
                    'target_value' => 100,
                    'current_value' => $goal['completion'],
                    'unit' => 'percent',
                    'weight' => 1.00,
                    'start_date' => '2026-01-01',
                    'due_date' => '2026-06-30',
                    'status' => $goal['status'],
                    'completion_percentage' => $goal['completion'],
                    'completed_at' => $goal['status'] === 'completed' ? Carbon::create(2026, 4, 15) : null,
                    'created_by' => $user->id,
                ]);
                $count++;
            }
        }

        $this->command?->line("Performance goals seeded: {$count} records.");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. PERFORMANCE FEEDBACK
    // ─────────────────────────────────────────────────────────────────────────

    private function seedPerformanceFeedback(): void
    {
        $feedbackTemplates = [
            ['type' => 'positive', 'category' => 'technical', 'content' => 'Excellent problem-solving skills demonstrated during the recent system migration. Code quality was top-notch.'],
            ['type' => 'positive', 'category' => 'collaboration', 'content' => 'Great team player who actively helps colleagues and shares knowledge during code reviews.'],
            ['type' => 'constructive', 'category' => 'technical', 'content' => 'Could improve documentation practices. Consider adding more inline comments for complex logic.'],
            ['type' => 'positive', 'category' => 'leadership', 'content' => 'Shows strong initiative in leading team discussions and proposing architectural improvements.'],
            ['type' => 'constructive', 'category' => 'collaboration', 'content' => 'Would benefit from more proactive communication during blockers. Consider daily async updates.'],
            ['type' => 'positive', 'category' => 'design', 'content' => 'Consistently delivers clean, user-friendly interfaces that align with design system guidelines.'],
            ['type' => 'constructive', 'category' => 'leadership', 'content' => 'Could take more ownership of cross-team coordination. Good potential for tech lead role.'],
            ['type' => 'positive', 'category' => 'technical', 'content' => 'Impressive attention to edge cases and error handling. Production incidents reduced significantly.'],
        ];

        $profileIds = collect($this->profiles)->pluck('id')->toArray();
        $count = 0;

        foreach ($this->profiles as $email => $profile) {
            $existingCount = PerformanceFeedback::where('staff_member_id', $profile->id)->count();

            if ($existingCount >= 3) {
                continue;
            }

            $needed = 3 - $existingCount;

            // Pick random givers (not self)
            $otherProfiles = collect($this->profiles)->filter(fn ($p) => $p->id !== $profile->id)->values();

            for ($i = 0; $i < $needed; $i++) {
                $template = $feedbackTemplates[($count + $i) % count($feedbackTemplates)];
                $giver = $otherProfiles->random();

                PerformanceFeedback::create([
                    'staff_member_id' => $profile->id,
                    'given_by' => $giver->id,
                    'feedback_type' => $template['type'],
                    'category' => $template['category'],
                    'content' => $template['content'],
                    'is_private' => false,
                    'acknowledged_at' => rand(0, 1) ? Carbon::create(2026, rand(3, 4), rand(1, 28)) : null,
                ]);
                $count++;
            }
        }

        $this->command?->line("Performance feedback seeded: {$count} records.");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 7. ATTENDANCE (2026-03 and 2026-04)
    // ─────────────────────────────────────────────────────────────────────────

    private function seedAttendance(): void
    {
        $months = [
            ['year' => 2026, 'month' => 3],
            ['year' => 2026, 'month' => 4],
        ];

        $statuses = ['present', 'present', 'present', 'present', 'present', 'present', 'present', 'present', 'present', 'present', 'present', 'present', 'present', 'present', 'present', 'late', 'late', 'sick_leave', 'absent', 'half_day'];

        $totalInserted = 0;

        foreach ($months as $period) {
            $startDate = Carbon::create($period['year'], $period['month'], 1);
            $endDate = $startDate->copy()->endOfMonth();
            $batch = [];

            foreach ($this->profiles as $email => $profile) {
                $date = $startDate->copy();

                while ($date->lte($endDate)) {
                    if ($date->isWeekend()) {
                        $date->addDay();

                        continue;
                    }

                    // Check if attendance already exists
                    $exists = Attendance::where('staff_member_id', $profile->id)
                        ->where('date', $date->format('Y-m-d'))
                        ->exists();

                    if ($exists) {
                        $date->addDay();

                        continue;
                    }

                    $status = $statuses[array_rand($statuses)];
                    $checkIn = null;
                    $checkOut = null;
                    $workedMinutes = null;

                    switch ($status) {
                        case 'present':
                            $checkIn = $date->copy()->setHour(8)->setMinute(rand(45, 59))->setSecond(0);
                            $checkOut = $date->copy()->setHour(17)->setMinute(rand(0, 30))->setSecond(0);
                            $workedMinutes = $checkIn->diffInMinutes($checkOut);
                            break;
                        case 'late':
                            $checkIn = $date->copy()->setHour(9)->setMinute(rand(5, 45))->setSecond(0);
                            $checkOut = $date->copy()->setHour(17)->setMinute(rand(0, 30))->setSecond(0);
                            $workedMinutes = $checkIn->diffInMinutes($checkOut);
                            break;
                        case 'half_day':
                            $checkIn = $date->copy()->setHour(9)->setMinute(0)->setSecond(0);
                            $checkOut = $date->copy()->setHour(13)->setMinute(0)->setSecond(0);
                            $workedMinutes = 240;
                            break;
                        case 'sick_leave':
                        case 'absent':
                            $workedMinutes = 0;
                            break;
                    }

                    $now = Carbon::now()->format('Y-m-d H:i:s');

                    $batch[] = [
                        'staff_member_id' => $profile->id,
                        'date' => $date->format('Y-m-d'),
                        'check_in' => $checkIn?->format('Y-m-d H:i:s'),
                        'check_out' => $checkOut?->format('Y-m-d H:i:s'),
                        'worked_minutes' => $workedMinutes,
                        'status' => $status,
                        'notes' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if (count($batch) >= 500) {
                        DB::table('attendances')->insert($batch);
                        $totalInserted += count($batch);
                        $batch = [];
                    }

                    $date->addDay();
                }
            }

            if (! empty($batch)) {
                DB::table('attendances')->insert($batch);
                $totalInserted += count($batch);
            }

            $this->command?->line("Attendance seeded for {$period['year']}-{$period['month']}: batch inserted.");
        }

        $this->command?->line("Attendance total inserted: {$totalInserted} records.");
    }
}
