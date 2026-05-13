<?php

namespace App\Models;

use App\Enums\PayrollStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payroll extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'salary_month',
        'attendance_period_id',
        'payroll_setting_version_id',
        'payment_date',
        'status',
        'processed_count',
        'correction_count',
    ];

    protected function casts(): array
    {
        return [
            'salary_month' => 'date',
            'payment_date' => 'date',
            'status' => PayrollStatus::class,
        ];
    }

    public function payrollDetails()
    {
        return $this->hasMany(PayrollDetail::class);
    }

    public function attendancePeriod()
    {
        return $this->belongsTo(AttendancePeriod::class, 'attendance_period_id');
    }

    public function payrollSettingVersion()
    {
        return $this->belongsTo(PayrollSettingVersion::class, 'payroll_setting_version_id');
    }

    public function activityLogs()
    {
        return $this->hasMany(PayrollActivityLog::class)->orderByDesc('occurred_at');
    }

    public function notificationDeliveries()
    {
        return $this->hasMany(PayrollNotificationDelivery::class)->orderByDesc('id');
    }

    public function approvals()
    {
        return $this->hasMany(PayrollApproval::class)->orderBy('id');
    }
}
