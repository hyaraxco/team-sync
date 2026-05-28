<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>TeamSync Analytics Report — {{ ucfirst(str_replace('_', ' ', $tab)) }}</title>
    <style>
        /* ── Reset & Base ───────────────────────────────────── */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Helvetica Neue', 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
            color: #1e293b;
            line-height: 1.6;
            background: #fff;
        }

        /* ── Cover Header ───────────────────────────────────── */
        .cover {
            background: linear-gradient(135deg, #0C51D9 0%, #1e40af 100%);
            color: #fff;
            padding: 40px 40px 32px;
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
        }
        .cover::after {
            content: '';
            position: absolute;
            top: -40px; right: -40px;
            width: 200px; height: 200px;
            background: rgba(255,255,255,0.06);
            border-radius: 50%;
        }
        .cover-brand {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            opacity: 0.75;
            margin-bottom: 8px;
        }
        .cover h1 {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }
        .cover-subtitle {
            font-size: 13px;
            font-weight: 400;
            opacity: 0.85;
            margin-bottom: 20px;
        }
        .cover-meta {
            display: table;
            width: 100%;
            border-top: 1px solid rgba(255,255,255,0.2);
            padding-top: 14px;
        }
        .cover-meta-cell {
            display: table-cell;
            font-size: 10px;
            opacity: 0.8;
            width: 33.33%;
        }
        .cover-meta-cell strong {
            display: block;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.6;
            margin-bottom: 2px;
        }

        /* ── Sections ───────────────────────────────────────── */
        .section {
            margin: 0 36px 24px;
        }
        .section-title {
            font-size: 13px;
            font-weight: 700;
            color: #0C51D9;
            padding-bottom: 6px;
            margin-bottom: 14px;
            border-bottom: 2px solid #0C51D9;
            letter-spacing: 0.3px;
        }

        /* ── Tables ─────────────────────────────────────────── */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        th {
            background: #f1f5f9;
            color: #475569;
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 9px 12px;
            text-align: left;
            border-bottom: 2px solid #cbd5e1;
        }
        th.text-right,
        td.text-right { text-align: right; }
        th.text-center,
        td.text-center { text-align: center; }
        td {
            padding: 8px 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 10px;
            color: #334155;
        }
        tr:nth-child(even) td { background: #fafbfc; }
        tr:hover td { background: #f0f4ff; }

        /* ── KPI Grid ───────────────────────────────────────── */
        .kpi-grid {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }
        .kpi-row { display: table-row; }
        .kpi-cell {
            display: table-cell;
            width: 33.33%;
            padding: 12px 16px;
            text-align: center;
            border: 1px solid #e2e8f0;
            background: #fafbfc;
        }
        .kpi-value {
            font-size: 24px;
            font-weight: 800;
            color: #0C51D9;
            line-height: 1.2;
        }
        .kpi-label {
            font-size: 8px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-top: 4px;
            font-weight: 600;
        }

        /* ── Summary Cards (2-column) ──────────────────────── */
        .summary-grid {
            display: table;
            width: 100%;
            margin-bottom: 16px;
        }
        .summary-row { display: table-row; }
        .summary-cell {
            display: table-cell;
            width: 50%;
            padding: 10px 16px;
            border: 1px solid #e2e8f0;
            background: #fff;
            vertical-align: top;
        }
        .summary-cell:first-child { border-right: none; }
        .summary-label {
            font-size: 8px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            font-weight: 600;
        }
        .summary-value {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            margin-top: 2px;
        }
        .summary-change {
            font-size: 9px;
            font-weight: 600;
            margin-top: 2px;
        }
        .change-positive { color: #16a34a; }
        .change-negative { color: #dc2626; }

        /* ── Badges ─────────────────────────────────────────── */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 8.5px;
            font-weight: 700;
            letter-spacing: 0.2px;
        }
        .badge-blue { background: #dbeafe; color: #1d4ed8; }
        .badge-green { background: #dcfce7; color: #15803d; }
        .badge-amber { background: #fef3c7; color: #b45309; }
        .badge-red { background: #fee2e2; color: #b91c1c; }
        .badge-purple { background: #ede9fe; color: #6d28d9; }

        /* ── Page Breaks ────────────────────────────────────── */
        .page-break { page-break-after: always; }

        /* ── Footer ─────────────────────────────────────────── */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 12px 36px;
            font-size: 8px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            display: table;
            width: 100%;
        }
        .footer-left { display: table-cell; text-align: left; }
        .footer-center { display: table-cell; text-align: center; }
        .footer-right { display: table-cell; text-align: right; }

        /* ── Utilities ──────────────────────────────────────── */
        .mb-4 { margin-bottom: 4px; }
        .mb-8 { margin-bottom: 8px; }
        .mb-16 { margin-bottom: 16px; }
        .text-muted { color: #94a3b8; }
        .text-small { font-size: 9px; }
        .currency { font-family: 'Courier New', monospace; font-size: 10px; }
    </style>
</head>
<body>

    {{-- ── Cover Header ─────────────────────────────────────── --}}
    <div class="cover">
        <div class="cover-brand">TeamSync HRIS</div>
        <h1>Analytics Report</h1>
        <div class="cover-subtitle">
            {{ ucfirst(str_replace('_', ' ', $tab)) }} Analytics
            @if($department) — {{ ucfirst($department) }} Department @endif
        </div>
        <div class="cover-meta">
            <div class="cover-meta-cell">
                <strong>Period</strong>
                {{ strtoupper($period) }}
            </div>
            <div class="cover-meta-cell">
                <strong>Department</strong>
                {{ $department ? ucfirst($department) : 'All Departments' }}
            </div>
            <div class="cover-meta-cell">
                <strong>Generated</strong>
                {{ $generatedAt }}
            </div>
        </div>
    </div>

    {{-- ── Executive Summary ────────────────────────────────── --}}
    @if($tab === 'executive')
        @if(!empty($data['kpis']))
        <div class="section">
            <div class="section-title">Key Performance Indicators</div>
            <div class="kpi-grid">
                <div class="kpi-row">
                    <div class="kpi-cell">
                        <div class="kpi-value">{{ number_format($data['kpis']['total_employees']) }}</div>
                        <div class="kpi-label">Total Employees</div>
                    </div>
                    <div class="kpi-cell">
                        <div class="kpi-value">{{ $data['kpis']['attendance_rate'] }}%</div>
                        <div class="kpi-label">Attendance Rate</div>
                    </div>
                    <div class="kpi-cell">
                        <div class="kpi-value">Rp {{ number_format($data['kpis']['average_salary'], 0, ',', '.') }}</div>
                        <div class="kpi-label">Average Salary</div>
                    </div>
                </div>
                <div class="kpi-row">
                    <div class="kpi-cell">
                        <div class="kpi-value">{{ $data['kpis']['active_projects'] }}</div>
                        <div class="kpi-label">Active Projects</div>
                    </div>
                    <div class="kpi-cell">
                        <div class="kpi-value">{{ $data['kpis']['task_completion_rate'] }}%</div>
                        <div class="kpi-label">Task Completion</div>
                    </div>
                    <div class="kpi-cell">
                        <div class="kpi-value">{{ $data['kpis']['leave_utilization'] }}%</div>
                        <div class="kpi-label">Leave Utilization</div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if(!empty($data['attendance_vs_deduction_trend']))
        <div class="section">
            <div class="section-title">Attendance vs Deduction Trend</div>
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th class="text-right">Attendance Rate</th>
                        <th class="text-right">Total Deductions</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($data['attendance_vs_deduction_trend'] as $row)
                    <tr>
                        <td>{{ $row['month'] }}</td>
                        <td class="text-right">{{ $row['attendance_rate'] }}%</td>
                        <td class="text-right currency">Rp {{ number_format($row['total_deductions'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if(!empty($data['monthly_hr_cost']))
        <div class="section">
            <div class="section-title">Monthly HR Cost Breakdown</div>
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th class="text-right">Salary</th>
                        <th class="text-right">Tax (PPh21)</th>
                        <th class="text-right">BPJS</th>
                        <th class="text-right">Deductions</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($data['monthly_hr_cost'] as $row)
                    <tr>
                        <td>{{ $row['month'] }}</td>
                        <td class="text-right currency">Rp {{ number_format($row['salary'], 0, ',', '.') }}</td>
                        <td class="text-right currency">Rp {{ number_format($row['tax'], 0, ',', '.') }}</td>
                        <td class="text-right currency">Rp {{ number_format($row['bpjs'], 0, ',', '.') }}</td>
                        <td class="text-right currency">Rp {{ number_format($row['deductions'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if(!empty($data['team_performance']))
        <div class="section">
            <div class="section-title">Team Performance Comparison</div>
            <table>
                <thead>
                    <tr>
                        <th>Team</th>
                        <th class="text-right">Members</th>
                        <th class="text-right">Attendance Rate</th>
                        <th class="text-right">Task Completion</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($data['team_performance'] as $row)
                    <tr>
                        <td>{{ $row['team_name'] }}</td>
                        <td class="text-right">{{ $row['member_count'] }}</td>
                        <td class="text-right">
                            <span class="badge {{ $row['attendance_rate'] >= 90 ? 'badge-green' : ($row['attendance_rate'] >= 75 ? 'badge-amber' : 'badge-red') }}">
                                {{ $row['attendance_rate'] }}%
                            </span>
                        </td>
                        <td class="text-right">
                            <span class="badge {{ (($row['task_completion_rate'] ?? $row['task_completion'] ?? 0) >= 80) ? 'badge-green' : ((($row['task_completion_rate'] ?? $row['task_completion'] ?? 0) >= 50) ? 'badge-amber' : 'badge-red') }}">
                                {{ $row['task_completion_rate'] ?? $row['task_completion'] ?? 0 }}%
                            </span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    @endif

    {{-- ── Workforce ───────────────────────────────────────── --}}
    @if($tab === 'workforce')
        @if(!empty($data['headcount_trend']))
        <div class="section">
            <div class="section-title">Headcount Trend</div>
            <table>
                <thead>
                    <tr><th>Month</th><th class="text-right">Headcount</th></tr>
                </thead>
                <tbody>
                @foreach($data['headcount_trend'] as $row)
                    <tr><td>{{ $row['month'] }}</td><td class="text-right">{{ $row['count'] }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @php
            $workforceSections = [
                'gender_distribution' => ['Gender Distribution', 'gender', 'Gender'],
                'employment_types' => ['Employment Types', 'type', 'Type'],
                'work_locations' => ['Work Locations', 'location', 'Location'],
                'department_headcount' => ['Department Headcount', 'department', 'Department'],
                'skill_levels' => ['PTKP Status Distribution', 'level', 'PTKP Status'],
                'age_distribution' => ['Age Distribution', 'range', 'Age Range'],
                'tenure_distribution' => ['Tenure Distribution', 'range', 'Tenure'],
            ];
        @endphp

        @foreach($workforceSections as $key => [$title, $field, $colLabel])
            @if(!empty($data[$key]))
            <div class="section">
                <div class="section-title">{{ $title }}</div>
                <table>
                    <thead>
                        <tr><th>{{ $colLabel }}</th><th class="text-right">Count</th></tr>
                    </thead>
                    <tbody>
                    @foreach($data[$key] as $row)
                        <tr>
                            <td>{{ ucfirst($row[$field]) }}</td>
                            <td class="text-right">{{ $row['count'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        @endforeach
    @endif

    {{-- ── Attendance ──────────────────────────────────────── --}}
    @if($tab === 'attendance')
        @if(!empty($data['monthly_attendance_rate']))
        <div class="section">
            <div class="section-title">Monthly Attendance Rate</div>
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th class="text-right">Rate</th>
                        <th class="text-right">Present</th>
                        <th class="text-right">Late</th>
                        <th class="text-right">Absent</th>
                        <th class="text-right">Half Day</th>
                        <th class="text-right">Sick</th>
                        <th class="text-right">Annual Leave</th>
                        <th class="text-right">Avg Hours</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($data['monthly_attendance_rate'] as $row)
                    <tr>
                        <td>{{ $row['month'] }}</td>
                        <td class="text-right">
                            <span class="badge {{ $row['attendance_rate'] >= 90 ? 'badge-green' : ($row['attendance_rate'] >= 75 ? 'badge-amber' : 'badge-red') }}">
                                {{ $row['attendance_rate'] }}%
                            </span>
                        </td>
                        <td class="text-right">{{ $row['present'] }}</td>
                        <td class="text-right">{{ $row['late'] }}</td>
                        <td class="text-right">{{ $row['absent'] }}</td>
                        <td class="text-right">{{ $row['half_day'] }}</td>
                        <td class="text-right">{{ $row['sick_leave'] }}</td>
                        <td class="text-right">{{ $row['annual_leave'] }}</td>
                        <td class="text-right">{{ $row['avg_hours'] }}h</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if(!empty($data['top_late_employees']))
        <div class="section">
            <div class="section-title">Top Late Employees</div>
            <table>
                <thead>
                    <tr><th>#</th><th>Name</th><th>Code</th><th class="text-right">Late Count</th></tr>
                </thead>
                <tbody>
                @foreach($data['top_late_employees'] as $idx => $row)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td>{{ $row['employee_name'] }}</td>
                        <td>{{ $row['employee_code'] }}</td>
                        <td class="text-right"><span class="badge badge-amber">{{ $row['late_count'] }}x</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if(!empty($data['correction_trend']))
        <div class="section">
            <div class="section-title">Correction Requests Trend</div>
            <table>
                <thead>
                    <tr><th>Month</th><th class="text-right">Total</th><th class="text-right">Approved</th><th class="text-right">Rejected</th><th class="text-right">Pending</th><th class="text-right">Approval Rate</th></tr>
                </thead>
                <tbody>
                @foreach($data['correction_trend'] as $row)
                    <tr>
                        <td>{{ $row['month'] }}</td>
                        <td class="text-right">{{ $row['total'] }}</td>
                        <td class="text-right"><span class="badge badge-green">{{ $row['approved'] }}</span></td>
                        <td class="text-right"><span class="badge badge-red">{{ $row['rejected'] }}</span></td>
                        <td class="text-right"><span class="badge badge-amber">{{ $row['pending'] }}</span></td>
                        <td class="text-right">{{ $row['approval_rate'] ?? '-' }}%</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    @endif

    {{-- ── Leave ───────────────────────────────────────────── --}}
    @if($tab === 'leave')
        @if(!empty($data['monthly_trend']))
        <div class="section">
            <div class="section-title">Monthly Leave Requests</div>
            <table>
                <thead>
                    <tr><th>Month</th><th class="text-right">Total</th><th class="text-right">Approved</th><th class="text-right">Rejected</th><th class="text-right">Pending</th></tr>
                </thead>
                <tbody>
                @foreach($data['monthly_trend'] as $row)
                    <tr>
                        <td>{{ $row['month'] }}</td>
                        <td class="text-right">{{ $row['total'] }}</td>
                        <td class="text-right"><span class="badge badge-green">{{ $row['approved'] }}</span></td>
                        <td class="text-right"><span class="badge badge-red">{{ $row['rejected'] }}</span></td>
                        <td class="text-right"><span class="badge badge-amber">{{ $row['pending'] }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if(!empty($data['type_distribution']))
        <div class="section">
            <div class="section-title">Leave Type Distribution</div>
            <table>
                <thead>
                    <tr><th>Leave Type</th><th class="text-right">Count</th><th class="text-right">Total Days</th></tr>
                </thead>
                <tbody>
                @foreach($data['type_distribution'] as $row)
                    <tr>
                        <td>{{ ucfirst(str_replace('_', ' ', $row['type'])) }}</td>
                        <td class="text-right">{{ $row['count'] }}</td>
                        <td class="text-right">{{ $row['total_days'] }}d</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if(!empty($data['top_leave_takers']))
        <div class="section">
            <div class="section-title">Top Leave Takers</div>
            <table>
                <thead>
                    <tr><th>#</th><th>Name</th><th>Code</th><th class="text-right">Total Days</th><th class="text-right">Requests</th></tr>
                </thead>
                <tbody>
                @foreach($data['top_leave_takers'] as $idx => $row)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td>{{ $row['employee_name'] }}</td>
                        <td>{{ $row['employee_code'] }}</td>
                        <td class="text-right"><span class="badge badge-purple">{{ $row['total_days'] }}d</span></td>
                        <td class="text-right">{{ $row['request_count'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if(!empty($data['approval_rate']))
        <div class="section">
            <div class="section-title">Approval Summary</div>
            <div class="kpi-grid">
                <div class="kpi-row">
                    <div class="kpi-cell">
                        <div class="kpi-value" style="color: #16a34a;">{{ $data['approval_rate']['approved'] ?? 0 }}</div>
                        <div class="kpi-label">Approved</div>
                    </div>
                    <div class="kpi-cell">
                        <div class="kpi-value" style="color: #dc2626;">{{ $data['approval_rate']['rejected'] ?? 0 }}</div>
                        <div class="kpi-label">Rejected</div>
                    </div>
                    <div class="kpi-cell">
                        <div class="kpi-value" style="color: #d97706;">{{ $data['approval_rate']['pending'] ?? 0 }}</div>
                        <div class="kpi-label">Pending</div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    @endif

    {{-- ── Payroll ─────────────────────────────────────────── --}}
    @if($tab === 'payroll')
        @if(!empty($data['cost_trend']))
        <div class="section">
            <div class="section-title">Payroll Cost Trend</div>
            <table>
                <thead>
                    <tr><th>Month</th><th class="text-right">Total Salary</th><th class="text-right">Deductions</th><th class="text-right">Employees</th><th class="text-right">Avg Salary</th></tr>
                </thead>
                <tbody>
                @foreach($data['cost_trend'] as $row)
                    <tr>
                        <td>{{ $row['month'] }}</td>
                        <td class="text-right currency">Rp {{ number_format($row['total_salary'], 0, ',', '.') }}</td>
                        <td class="text-right currency">Rp {{ number_format($row['total_deductions'], 0, ',', '.') }}</td>
                        <td class="text-right">{{ $row['employee_count'] }}</td>
                        <td class="text-right currency">Rp {{ number_format($row['avg_salary'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if(!empty($data['tax_bpjs_trend']))
        <div class="section">
            <div class="section-title">Tax & BPJS Contributions Trend</div>
            <table>
                <thead>
                    <tr><th>Month</th><th class="text-right">PPh21</th><th class="text-right">BPJS TK</th><th class="text-right">BPJS Kesehatan</th></tr>
                </thead>
                <tbody>
                @foreach($data['tax_bpjs_trend'] as $row)
                    <tr>
                        <td>{{ $row['month'] }}</td>
                        <td class="text-right currency">Rp {{ number_format($row['pph21'], 0, ',', '.') }}</td>
                        <td class="text-right currency">Rp {{ number_format($row['bpjs_tk'], 0, ',', '.') }}</td>
                        <td class="text-right currency">Rp {{ number_format($row['bpjs_kes'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if(!empty($data['cost_by_department']))
        <div class="section">
            <div class="section-title">Cost by Department</div>
            <table>
                <thead>
                    <tr><th>Department</th><th class="text-right">Total Cost</th><th class="text-right">Avg Salary</th><th class="text-right">Employees</th></tr>
                </thead>
                <tbody>
                @foreach($data['cost_by_department'] as $row)
                    <tr>
                        <td>{{ ucfirst($row['department']) }}</td>
                        <td class="text-right currency">Rp {{ number_format($row['total_cost'], 0, ',', '.') }}</td>
                        <td class="text-right currency">Rp {{ number_format($row['avg_salary'], 0, ',', '.') }}</td>
                        <td class="text-right">{{ $row['employee_count'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    @endif

    {{-- ── Projects ────────────────────────────────────────── --}}
    @if($tab === 'projects')
        @if(!empty($data['task_velocity']))
        <div class="section">
            <div class="section-title">Task Velocity</div>
            <table>
                <thead>
                    <tr><th>Month</th><th class="text-right">Tasks Completed</th></tr>
                </thead>
                <tbody>
                @foreach($data['task_velocity'] as $row)
                    <tr><td>{{ $row['month'] }}</td><td class="text-right">{{ $row['completed'] }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if(!empty($data['task_status_distribution']))
        <div class="section">
            <div class="section-title">Task Status Distribution</div>
            <table>
                <thead>
                    <tr><th>Status</th><th class="text-right">Count</th></tr>
                </thead>
                <tbody>
                @foreach($data['task_status_distribution'] as $row)
                    <tr>
                        <td><span class="badge badge-blue">{{ ucfirst($row['status']) }}</span></td>
                        <td class="text-right">{{ $row['count'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if(!empty($data['team_productivity']))
        <div class="section">
            <div class="section-title">Team Productivity</div>
            <table>
                <thead>
                    <tr><th>Team</th><th class="text-right">Tasks Completed</th></tr>
                </thead>
                <tbody>
                @foreach($data['team_productivity'] as $row)
                    <tr>
                        <td>{{ $row['team_name'] }}</td>
                        <td class="text-right"><span class="badge badge-green">{{ $row['completed'] }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    @endif

    {{-- ── Performance ─────────────────────────────────────── --}}
    @if($tab === 'performance')
        @php
            $company = $data['company_summary'] ?? [];
            $rating = $data['rating_distribution'] ?? [];
            $goals = $data['goal_completion'] ?? [];
            $feedback = $data['feedback_metrics'] ?? [];
        @endphp

        <div class="section">
            <div class="section-title">Company Performance Summary</div>
            <div class="kpi-grid">
                <div class="kpi-row">
                    <div class="kpi-cell">
                        <div class="kpi-value">{{ $company['total_reviews'] ?? 0 }}</div>
                        <div class="kpi-label">Total Reviews</div>
                    </div>
                    <div class="kpi-cell">
                        <div class="kpi-value">{{ $company['completed_reviews'] ?? 0 }}</div>
                        <div class="kpi-label">Completed</div>
                    </div>
                    <div class="kpi-cell">
                        <div class="kpi-value">{{ $company['completion_rate'] ?? 0 }}%</div>
                        <div class="kpi-label">Completion Rate</div>
                    </div>
                </div>
                <div class="kpi-row">
                    <div class="kpi-cell">
                        <div class="kpi-value">{{ $company['average_rating'] ?? '-' }}</div>
                        <div class="kpi-label">Average Rating</div>
                    </div>
                    <div class="kpi-cell">
                        <div class="kpi-value">{{ $goals['total_goals'] ?? 0 }}</div>
                        <div class="kpi-label">Total Goals</div>
                    </div>
                    <div class="kpi-cell">
                        <div class="kpi-value">{{ $feedback['total_feedback'] ?? 0 }}</div>
                        <div class="kpi-label">Total Feedback</div>
                    </div>
                </div>
            </div>
        </div>

        @if(!empty($rating['distribution']))
        <div class="section">
            <div class="section-title">Rating Distribution</div>
            <table>
                <thead>
                    <tr><th>Rating</th><th class="text-right">Count</th><th class="text-right">Percentage</th></tr>
                </thead>
                <tbody>
                @foreach($rating['distribution'] as $score => $count)
                    <tr>
                        <td>
                            <span class="badge {{ $score >= 4 ? 'badge-green' : ($score >= 3 ? 'badge-amber' : 'badge-red') }}">
                                {{ $score }} {{ $score == 1 ? 'star' : 'stars' }}
                            </span>
                        </td>
                        <td class="text-right">{{ $count }}</td>
                        <td class="text-right">{{ $rating['percentages'][$score] ?? 0 }}%</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <div class="section">
            <div class="section-title">Goal Completion</div>
            <table>
                <thead>
                    <tr><th>Metric</th><th class="text-right">Value</th></tr>
                </thead>
                <tbody>
                    <tr><td>Total Goals</td><td class="text-right">{{ $goals['total_goals'] ?? 0 }}</td></tr>
                    <tr><td>Completed</td><td class="text-right"><span class="badge badge-green">{{ $goals['completed_goals'] ?? 0 }}</span></td></tr>
                    <tr><td>In Progress</td><td class="text-right"><span class="badge badge-blue">{{ $goals['in_progress_goals'] ?? 0 }}</span></td></tr>
                    <tr><td>Not Started</td><td class="text-right"><span class="badge badge-amber">{{ $goals['not_started_goals'] ?? 0 }}</span></td></tr>
                    <tr><td>Completion Rate</td><td class="text-right">{{ $goals['completion_rate'] ?? 0 }}%</td></tr>
                    <tr><td>Average Progress</td><td class="text-right">{{ $goals['average_progress'] ?? 0 }}%</td></tr>
                    <tr><td>Overdue Goals</td><td class="text-right"><span class="badge badge-red">{{ $goals['overdue_goals'] ?? 0 }}</span></td></tr>
                </tbody>
            </table>
        </div>

        @if(!empty($goals['by_category']))
        <div class="section">
            <div class="section-title">Goals by Category</div>
            <table>
                <thead>
                    <tr><th>Category</th><th class="text-right">Total</th><th class="text-right">Completed</th><th class="text-right">Completion Rate</th></tr>
                </thead>
                <tbody>
                @foreach($goals['by_category'] as $category => $stats)
                    <tr>
                        <td>{{ $category ?: 'Uncategorized' }}</td>
                        <td class="text-right">{{ $stats['total'] ?? 0 }}</td>
                        <td class="text-right">{{ $stats['completed'] ?? 0 }}</td>
                        <td class="text-right">
                            <span class="badge {{ ($stats['completion_rate'] ?? 0) >= 80 ? 'badge-green' : (($stats['completion_rate'] ?? 0) >= 50 ? 'badge-amber' : 'badge-red') }}">
                                {{ $stats['completion_rate'] ?? 0 }}%
                            </span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <div class="section">
            <div class="section-title">Feedback Metrics</div>
            <table>
                <thead>
                    <tr><th>Metric</th><th class="text-right">Value</th></tr>
                </thead>
                <tbody>
                    <tr><td>Total Feedback</td><td class="text-right">{{ $feedback['total_feedback'] ?? 0 }}</td></tr>
                    <tr><td>Recent Feedback (Last 30 days)</td><td class="text-right">{{ $feedback['recent_feedback_30d'] ?? 0 }}</td></tr>
                    <tr><td>Average per Employee</td><td class="text-right">{{ $feedback['average_per_employee'] ?? 0 }}</td></tr>
                    @foreach(($feedback['by_type'] ?? []) as $type => $count)
                        <tr><td>Type: {{ ucfirst($type) }}</td><td class="text-right">{{ $count }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- ── Footer ──────────────────────────────────────────── --}}
    <div class="footer">
        <div class="footer-left">TeamSync HRIS</div>
        <div class="footer-center">Analytics Report — {{ ucfirst(str_replace('_', ' ', $tab)) }}</div>
        <div class="footer-right">Confidential • {{ $generatedAt }}</div>
    </div>

</body>
</html>
