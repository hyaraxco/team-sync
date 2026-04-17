<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendancePeriod extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_REVIEW = 'review';
    public const STATUS_LOCKED = 'locked';

    protected $fillable = [
        'start_date',
        'end_date',
        'cutoff_date',
        'status',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'cutoff_date' => 'date',
            'locked_at' => 'datetime',
        ];
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'attendance_period_id');
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class, 'attendance_period_id');
    }

    public function sourceAdjustments()
    {
        return $this->hasMany(PayrollAdjustment::class, 'source_period_id');
    }

    public function targetAdjustments()
    {
        return $this->hasMany(PayrollAdjustment::class, 'target_period_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isReview(): bool
    {
        return $this->status === self::STATUS_REVIEW;
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }
}
