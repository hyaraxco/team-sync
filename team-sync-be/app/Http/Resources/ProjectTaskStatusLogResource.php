<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectTaskStatusLogResource extends JsonResource
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
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'reason' => $this->reason,
            'changed_at' => $this->changed_at,
            'changed_by' => $this->changed_by,
            'changed_by_employee' => new EmployeeProfileResource($this->whenLoaded('changedBy')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
