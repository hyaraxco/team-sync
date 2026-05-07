<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectTaskResource extends JsonResource
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
            'project_id' => $this->project_id,
            'name' => $this->name,
            'description' => $this->description,
            'assignee_id' => $this->assignee_id,
            'priority' => $this->priority,
            'status' => $this->status,
            'rejected_reason' => $this->rejected_reason,
            'rejected_by' => $this->rejected_by,
            'rejected_at' => $this->rejected_at,
            'needs_revision' => $this->needs_revision,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'project' => new ProjectResource($this->whenLoaded('project')),
            'assignee' => new StaffMemberProfileResource($this->whenLoaded('assignee')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
