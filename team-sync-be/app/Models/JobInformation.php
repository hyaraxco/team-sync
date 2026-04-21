<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobInformation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'staff_member_id',
        'job_title',
        'team_id',
        'status',
        'employment_type',
        'work_location',
        'start_date',
        'monthly_salary',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'monthly_salary' => 'decimal:2',
        ];
    }

    public function staffMember()
    {
        return $this->belongsTo(StaffMemberProfile::class, 'staff_member_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function attendancePolicy()
    {
        return $this->hasOne(AttendancePolicy::class, 'employment_type', 'employment_type');
    }
}
