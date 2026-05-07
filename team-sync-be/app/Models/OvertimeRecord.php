<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OvertimeRecord extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const TYPE_WORKDAY = 'workday';

    public const TYPE_WEEKEND = 'weekend';

    public const TYPE_HOLIDAY = 'holiday';

    // Indonesian labor regulation limits (Kepmenakertrans KEP.102/MEN/VI/2004)
    public const MAX_HOURS_PER_DAY = 4;

    public const MAX_HOURS_PER_WEEK = 18;

    protected $fillable = [
        'staff_member_id',
        'attendance_id',
        'date',
        'start_time',
        'end_time',
        'hours',
        'overtime_type',
        'status',
        'approved_by',
        'approved_at',
        'notes',
        'rejection_reason',
        'company_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'hours' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────────────────

    public function staffMember()
    {
        return $this->belongsTo(StaffMemberProfile::class, 'staff_member_id');
    }

    public function attendance()
    {
        return $this->belongsTo(Attendance::class, 'attendance_id');
    }

    public function approvedByUser()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────────────────

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeForPeriod(Builder $query, $start, $end): Builder
    {
        return $query->whereBetween('date', [$start, $end]);
    }

    public function scopeForStaffMember(Builder $query, int $staffMemberId): Builder
    {
        return $query->where('staff_member_id', $staffMemberId);
    }
}
