<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectTaskAttachment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_task_id',
        'staff_member_id',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
    ];

    public function task()
    {
        return $this->belongsTo(ProjectTask::class, 'project_task_id');
    }

    public function staffMember()
    {
        return $this->belongsTo(StaffMemberProfile::class, 'staff_member_id');
    }
}
