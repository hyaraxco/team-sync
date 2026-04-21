<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'date',
        'attendance_period_id',
        'check_in',
        'check_in_lat',
        'check_in_long',
        'check_out',
        'worked_minutes',
        'actual_work_mode',
        'policy_mismatch_flag',
        'check_out_lat',
        'check_out_long',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'check_in' => 'datetime',
            'check_out' => 'datetime',
            'worked_minutes' => 'integer',
            'policy_mismatch_flag' => 'boolean',
            'check_in_lat' => 'decimal:8',
            'check_in_long' => 'decimal:8',
            'check_out_lat' => 'decimal:8',
            'check_out_long' => 'decimal:8',
        ];
    }

    public function staffMember()
    {
        return $this->belongsTo(StaffMemberProfile::class, 'employee_id');
    }

    public function attendancePeriod()
    {
        return $this->belongsTo(AttendancePeriod::class, 'attendance_period_id');
    }

    public function policyMismatch()
    {
        return $this->hasOne(AttendancePolicyMismatch::class);
    }
}
