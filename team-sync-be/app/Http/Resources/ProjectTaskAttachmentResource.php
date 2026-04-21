<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectTaskAttachmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_task_id' => $this->project_task_id,
            'staff_member_id' => $this->staff_member_id,
            'file_name' => $this->file_name,
            'file_path' => $this->file_path,
            'file_url' => $this->file_path ? asset('storage/'.$this->file_path) : null,
            'file_size' => $this->file_size,
            'mime_type' => $this->mime_type,
            'staff_member' => new StaffMemberProfileResource($this->whenLoaded('staffMember')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
