<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>TeamSync Analytics Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #1a1a2e; line-height: 1.5; }
        .header { background: #0C51D9; color: white; padding: 20px 30px; margin-bottom: 20px; }
        .header h1 { font-size: 20px; font-weight: 700; }
        .header .meta { font-size: 10px; opacity: 0.8; margin-top: 4px; }
        .section { margin: 0 30px 20px; }
        .section-title { font-size: 14px; font-weight: 700; color: #0C51D9; border-bottom: 2px solid #0C51D9; padding-bottom: 4px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th { background: #f1f5f9; color: #475569; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; padding: 8px 10px; text-align: left; border-bottom: 2px solid #e2e8f0; }
        td { padding: 7px 10px; border-bottom: 1px solid #f1f5f9; font-size: 11px; }
        tr:nth-child(even) td { background: #fafafa; }
        .kpi-grid { display: table; width: 100%; }
        .kpi-row { display: table-row; }
        .kpi-cell { display: table-cell; width: 33.33%; padding: 8px; text-align: center; }
        .kpi-value { font-size: 22px; font-weight: 700; color: #0C51D9; }
        .kpi-label { font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; padding: 10px 30px; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; text-align: center; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: 600; }
        .badge-blue { background: #dbeafe; color: #1d4ed8; }
        .badge-green { background: #dcfce7; color: #15803d; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    <div class="header">
        <h1>TeamSync Analytics Report</h1>
        <div class="meta">
            {{ ucfirst(str_replace('_', ' ', $tab)) }} Analytics
            &bull; Period: {{ $period }}
            @if($department) &bull; Department: {{ ucfirst($department) }} @endif
            &bull; Generated: {{ $generatedAt }}
        </div>
    </div>

    @if($tab === 'executive')
        @if(!empty($data['kpis']))
        <div class="section">
            <div class="section-title">Key Performance Indicators</div>
            <table>
                <thead>
                    <tr>
                        <th>Metric</th>
                        <th class="text-right">Value</th>
                        <th class="text-right">Change</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>Total Employees</td><td class="text-right">{{ number_format($data['kpis']['total_employees']) }}</td><td class="text-right">{{ $data['kpis']['employee_growth'] }}%</td></tr>
                    <tr><td>Attendance Rate</td><td class="text-right">{{ $data['kpis']['attendance_rate'] }}%</td><td class="text-right">{{ $data['kpis']['attendance_rate_change'] }}%</td></tr>
                    <tr><td>Average Salary</td><td class="text-right">Rp {{ number_format($data['kpis']['average_salary'], 0, ',', '.') }}</td><td class="text-right">{{ $data['kpis']['salary_change'] }}%</td></tr>
                    <tr><td>Active Projects</td><td class="text-right">{{ $data['kpis']['active_projects'] }}</td><td class="text-right">-</td></tr>
                    <tr><td>Task Completion Rate</td><td class="text-right">{{ $data['kpis']['task_completion_rate'] }}%</td><td class="text-right">-</td></tr>
                    <tr><td>Leave Utilization</td><td class="text-right">{{ $data['kpis']['leave_utilization'] }}%</td><td class="text-right">-</td></tr>
                </tbody>
            </table>
        </div>
        @endif

        @if(!empty($data['monthly_hr_cost']))
        <div class="section">
            <div class="section-title">Monthly HR Cost Breakdown</div>
            <table>
                <thead><tr><th>Month</th><th class="text-right">Salary</th><th class="text-right">Tax</th><th class="text-right">BPJS</th><th class="text-right">Deductions</th></tr></thead>
                <tbody>
                @foreach($data['monthly_hr_cost'] as $row)
                    <tr>
                        <td>{{ $row['month'] }}</td>
                        <td class="text-right">Rp {{ number_format($row['salary'], 0, ',', '.') }}</td>
                        <td class="text-right">Rp {{ number_format($row['tax'], 0, ',', '.') }}</td>
                        <td class="text-right">Rp {{ number_format($row['bpjs'], 0, ',', '.') }}</td>
                        <td class="text-right">Rp {{ number_format($row['deductions'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if(!empty($data['team_performance']))
        <div class="section">
            <div class="section-title">Team Performance</div>
            <table>
                <thead><tr><th>Team</th><th class="text-right">Members</th><th class="text-right">Attendance Rate</th><th class="text-right">Task Completion</th></tr></thead>
                <tbody>
                @foreach($data['team_performance'] as $row)
                    <tr>
                        <td>{{ $row['team_name'] }}</td>
                        <td class="text-right">{{ $row['member_count'] }}</td>
                        <td class="text-right">{{ $row['attendance_rate'] }}%</td>
                        <td class="text-right">{{ $row['task_completion'] }}%</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    @endif

    @if($tab === 'attendance' && !empty($data['monthly_attendance_rate']))
    <div class="section">
        <div class="section-title">Monthly Attendance Rate</div>
        <table>
            <thead><tr><th>Month</th><th class="text-right">Rate</th><th class="text-right">Present</th><th class="text-right">Late</th><th class="text-right">Absent</th><th class="text-right">Avg Hours</th></tr></thead>
            <tbody>
            @foreach($data['monthly_attendance_rate'] as $row)
                <tr>
                    <td>{{ $row['month'] }}</td>
                    <td class="text-right">{{ $row['attendance_rate'] }}%</td>
                    <td class="text-right">{{ $row['present'] }}</td>
                    <td class="text-right">{{ $row['late'] }}</td>
                    <td class="text-right">{{ $row['absent'] }}</td>
                    <td class="text-right">{{ $row['avg_hours'] }}h</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if($tab === 'leave' && !empty($data['monthly_trend']))
    <div class="section">
        <div class="section-title">Monthly Leave Requests</div>
        <table>
            <thead><tr><th>Month</th><th class="text-right">Total</th><th class="text-right">Approved</th><th class="text-right">Rejected</th><th class="text-right">Pending</th></tr></thead>
            <tbody>
            @foreach($data['monthly_trend'] as $row)
                <tr>
                    <td>{{ $row['month'] }}</td>
                    <td class="text-right">{{ $row['total'] }}</td>
                    <td class="text-right">{{ $row['approved'] }}</td>
                    <td class="text-right">{{ $row['rejected'] }}</td>
                    <td class="text-right">{{ $row['pending'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if($tab === 'payroll' && !empty($data['cost_trend']))
    <div class="section">
        <div class="section-title">Payroll Cost Trend</div>
        <table>
            <thead><tr><th>Month</th><th class="text-right">Total Salary</th><th class="text-right">Deductions</th><th class="text-right">Employees</th><th class="text-right">Avg Salary</th></tr></thead>
            <tbody>
            @foreach($data['cost_trend'] as $row)
                <tr>
                    <td>{{ $row['month'] }}</td>
                    <td class="text-right">Rp {{ number_format($row['total_salary'], 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($row['total_deductions'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ $row['employee_count'] }}</td>
                    <td class="text-right">Rp {{ number_format($row['avg_salary'], 0, ',', '.') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if($tab === 'workforce' && !empty($data['headcount_trend']))
    <div class="section">
        <div class="section-title">Headcount Trend</div>
        <table>
            <thead><tr><th>Month</th><th class="text-right">Headcount</th></tr></thead>
            <tbody>
            @foreach($data['headcount_trend'] as $row)
                <tr><td>{{ $row['month'] }}</td><td class="text-right">{{ $row['count'] }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if($tab === 'projects' && !empty($data['task_velocity']))
    <div class="section">
        <div class="section-title">Task Velocity</div>
        <table>
            <thead><tr><th>Month</th><th class="text-right">Tasks Completed</th></tr></thead>
            <tbody>
            @foreach($data['task_velocity'] as $row)
                <tr><td>{{ $row['month'] }}</td><td class="text-right">{{ $row['completed'] }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="footer">
        TeamSync HRIS &bull; Analytics Report &bull; Generated {{ $generatedAt }} &bull; Confidential
    </div>
</body>
</html>
