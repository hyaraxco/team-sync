<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollSettingVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_setting_id',
        'version_number',
        'payday_day',
        'attendance_cutoff_day',
        'working_days_mode',
        'default_working_days',
        'absent_deduction_rate',
        'rounding_mode',
        'rounding_unit',
        'note_template',
        'updated_by',
        'effective_at',
    ];

    protected function casts(): array
    {
        return [
            'absent_deduction_rate' => 'decimal:2',
            'effective_at' => 'datetime',
        ];
    }

    public function payrollSetting()
    {
        return $this->belongsTo(PayrollSetting::class, 'payroll_setting_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class, 'payroll_setting_version_id');
    }
}
