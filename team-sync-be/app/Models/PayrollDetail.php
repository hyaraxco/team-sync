<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $payroll_id
 * @property int $staff_member_id
 */
class PayrollDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'payroll_id',
        'staff_member_id',
        'original_salary',
        'final_salary',
        'effective_working_days',
        'daily_rate',
        'attended_days',
        'present_days',
        'late_days',
        'half_day_count',
        'paid_leave_days',
        'unpaid_leave_days',
        'holiday_days',
        'sick_days',
        'absent_days',
        'deduction_days',
        'deduction_amount',
        'overtime_hours',
        'overtime_amount',
        'overtime_records_count',
        'policy_mismatch_days',
        'warning_flags',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'original_salary' => 'decimal:2',
            'final_salary' => 'decimal:2',
            'daily_rate' => 'decimal:2',
            'deduction_days' => 'decimal:2',
            'deduction_amount' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
            'overtime_amount' => 'decimal:2',
            'warning_flags' => 'array',
        ];
    }

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }

    public function staffMember()
    {
        return $this->belongsTo(StaffMemberProfile::class, 'staff_member_id');
    }

    public function appliedAdjustments()
    {
        return $this->hasMany(PayrollAdjustment::class, 'staff_member_id', 'staff_member_id');
    }

    public function notificationDeliveries()
    {
        return $this->hasMany(PayrollNotificationDelivery::class);
    }
}
