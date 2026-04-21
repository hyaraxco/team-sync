<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectTaskStatusLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_task_id',
        'from_status',
        'to_status',
        'changed_by',
        'reason',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    public function task()
    {
        return $this->belongsTo(ProjectTask::class, 'project_task_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(StaffMemberProfile::class, 'changed_by');
    }
}
