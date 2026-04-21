<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceCorrection extends Model {
    use HasFactory;

    protected $fillable = [
        'attendance_id',
        'employee_id',
        'original_check_in',
        'original_check_out',
        'requested_check_in',
        'requested_check_out',
        'reason',
        'status',
        'reviewed_by',
        'review_notes',
    ];

    protected function casts(): array {
        return [
            'original_check_in' => 'datetime',
            'original_check_out' => 'datetime',
            'requested_check_in' => 'datetime',
            'requested_check_out' => 'datetime',
        ];
    }

    public function attendance() {
        return $this->belongsTo(Attendance::class);
    }

    public function staffMember() {
        return $this->belongsTo(StaffMemberProfile::class);
    }

    public function reviewer() {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
