<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectTask extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'name',
        'description',
        'assignee_id',
        'priority',
        'status',
        'rejected_reason',
        'rejected_by',
        'rejected_at',
        'needs_revision',
        'due_date',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'rejected_at' => 'datetime',
            'needs_revision' => 'boolean',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee()
    {
        return $this->belongsTo(StaffMemberProfile::class, 'assignee_id');
    }

    public function comments()
    {
        return $this->hasMany(ProjectTaskComment::class, 'project_task_id');
    }

    public function attachments()
    {
        return $this->hasMany(ProjectTaskAttachment::class, 'project_task_id');
    }

    public function statusLogs()
    {
        return $this->hasMany(ProjectTaskStatusLog::class, 'project_task_id');
    }
}
