<?php

namespace App\Models;

use App\Enums\LeaveType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'leave_type',
        'start_date',
        'end_date',
        'total_days',
        'reason',
        'emergency_contact',
        'proof_file_path',
        'proof_file_name',
        'proof_mime_type',
        'proof_size_kb',
        'proof_uploaded_at',
        'proof_review_status',
        'status',
        'approved_by',
        'proof_reviewed_by',
        'proof_reviewed_at',
        'proof_review_notes',
    ];

    protected function casts(): array
    {
        return [
            'leave_type' => LeaveType::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'proof_uploaded_at' => 'datetime',
            'proof_reviewed_at' => 'datetime',
        ];
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($query) use ($search) {
            $query->where('reason', 'like', "%{$search}%")
                ->orWhere('leave_type', 'like', "%{$search}%")
                ->orWhere('emergency_contact', 'like', "%{$search}%")
                ->orWhereHas('staffMember.user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
        });
    }

    public function staffMember()
    {
        return $this->belongsTo(StaffMemberProfile::class, 'employee_id');
    }

    public function approver()
    {
        return $this->belongsTo(StaffMemberProfile::class, 'approved_by');
    }

    public function proofReviewedBy()
    {
        return $this->belongsTo(StaffMemberProfile::class, 'proof_reviewed_by');
    }

    public function payrollAdjustments()
    {
        return $this->hasMany(PayrollAdjustment::class, 'source_reference_id')
            ->where('source_reference_type', 'leave_request');
    }
}
