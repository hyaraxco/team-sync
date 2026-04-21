<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectTaskCommentResource extends JsonResource
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
            'comment' => $this->comment,
            'staff_member' => new StaffMemberProfileResource($this->whenLoaded('staffMember')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
