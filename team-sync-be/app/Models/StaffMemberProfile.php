<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

/**
 * @property int $id
 * @property int $user_id
 * @property string $code
 */
class StaffMemberProfile extends Model
{
    use BelongsToCompany, HasFactory, Searchable, SoftDeletes;

    protected $appends = ['full_name', 'email'];

    protected $fillable = [
        'user_id',
        'code',
        'identity_number',
        'npwp',
        'bpjs_ketenagakerjaan',
        'bpjs_kesehatan',
        'ptkp_status',
        'phone',
        'date_of_birth',
        'gender',
        'religion',
        'marital_status',
        'blood_type',
        'place_of_birth',
        'address',
        'city',
        'postal_code',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    public function getFullNameAttribute(): ?string
    {
        return $this->user?->name;
    }

    public function getEmailAttribute(): ?string
    {
        return $this->user?->email;
    }

    /**
     * Get the indexable data array for the model.
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'phone' => $this->phone,
            'identity_number' => $this->identity_number,
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function jobInformation()
    {
        return $this->hasOne(JobInformation::class, 'staff_member_id');
    }

    public function bankInformation()
    {
        return $this->hasOne(BankInformation::class, 'staff_member_id');
    }

    public function emergencyContacts()
    {
        return $this->hasMany(EmergencyContact::class, 'staff_member_id');
    }

    public function teamMembers()
    {
        return $this->hasMany(TeamMember::class, 'staff_member_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class, 'id', 'id')
            ->join('team_members', 'teams.id', '=', 'team_members.team_id')
            ->where('team_members.staff_member_id', $this->id);
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class, 'team_members', 'staff_member_id', 'team_id');
    }

    public function ledProjects()
    {
        return $this->hasMany(Project::class, 'project_leader_id');
    }

    public function assignedTasks()
    {
        return $this->hasMany(ProjectTask::class, 'assignee_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'staff_member_id');
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class, 'staff_member_id');
    }

    public function approvedLeaveRequests()
    {
        return $this->hasMany(LeaveRequest::class, 'approved_by');
    }

    public function proofReviewedLeaveRequests()
    {
        return $this->hasMany(LeaveRequest::class, 'proof_reviewed_by');
    }

    public function payrollDetails()
    {
        return $this->hasMany(PayrollDetail::class, 'staff_member_id');
    }

    public function hybridWorkSchedules()
    {
        return $this->hasMany(HybridWorkSchedule::class, 'staff_member_id');
    }

    public function hybridScheduleOverrides()
    {
        return $this->hasMany(HybridScheduleOverride::class, 'staff_member_id');
    }

    public function attendancePolicyMismatches()
    {
        return $this->hasMany(AttendancePolicyMismatch::class, 'staff_member_id');
    }

    public function payrollAdjustments()
    {
        return $this->hasMany(PayrollAdjustment::class, 'staff_member_id');
    }

    public function overtimeRecords()
    {
        return $this->hasMany(OvertimeRecord::class, 'staff_member_id');
    }
}
