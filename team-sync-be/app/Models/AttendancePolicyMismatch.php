<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendancePolicyMismatch extends Model
{
    use HasFactory;

    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_ESCALATED_HR = 'escalated_hr';

    public const STATUS_RESOLVED = 'resolved';

    public const UNRESOLVED_STATUSES = [
        self::STATUS_PENDING_REVIEW,
        self::STATUS_ACKNOWLEDGED,
        self::STATUS_ESCALATED_HR,
    ];

    protected $fillable = [
        'attendance_id',
        'employee_id',
        'mismatch_date',
        'planned_work_mode',
        'actual_work_mode',
        'status',
        'acknowledged_by',
        'acknowledged_at',
        'escalated_at',
        'resolved_by',
        'resolved_at',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'mismatch_date' => 'date',
            'acknowledged_at' => 'datetime',
            'escalated_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    public function employee()
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_id');
    }

    public function acknowledgedBy()
    {
        return $this->belongsTo(EmployeeProfile::class, 'acknowledged_by');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(EmployeeProfile::class, 'resolved_by');
    }
}
