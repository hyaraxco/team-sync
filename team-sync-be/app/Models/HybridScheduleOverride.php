<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HybridScheduleOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'date',
        'planned_work_mode',
        'reason',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function staffMember()
    {
        return $this->belongsTo(StaffMemberProfile::class, 'employee_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(StaffMemberProfile::class, 'requested_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(StaffMemberProfile::class, 'approved_by');
    }
}
