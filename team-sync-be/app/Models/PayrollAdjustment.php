<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollAdjustment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_APPLIED = 'applied';

    public const KIND_PAID_LEAVE_REVERSAL = 'paid_leave_reversal';

    public const KIND_PAID_LEAVE_CREDIT = 'paid_leave_credit';

    public const KIND_ABSENCE_CORRECTION_CREDIT = 'absence_correction_credit';

    public const KIND_ABSENCE_CORRECTION_DEDUCTION = 'absence_correction_deduction';

    protected $fillable = [
        'staff_member_id',
        'source_period_id',
        'target_period_id',
        'source_reference_type',
        'source_reference_id',
        'adjustment_kind',
        'days_delta',
        'amount_delta',
        'reason',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'days_delta' => 'decimal:2',
            'amount_delta' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function staffMember()
    {
        return $this->belongsTo(StaffMemberProfile::class, 'staff_member_id');
    }

    public function sourcePeriod()
    {
        return $this->belongsTo(AttendancePeriod::class, 'source_period_id');
    }

    public function targetPeriod()
    {
        return $this->belongsTo(AttendancePeriod::class, 'target_period_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function leaveRequestSource()
    {
        return $this->belongsTo(LeaveRequest::class, 'source_reference_id');
    }

    public function scopeApprovedForTargetPeriod($query, int $targetPeriodId)
    {
        return $query
            ->where('target_period_id', $targetPeriodId)
            ->where('status', self::STATUS_APPROVED);
    }
}
