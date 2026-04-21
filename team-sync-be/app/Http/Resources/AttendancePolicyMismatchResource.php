<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendancePolicyMismatchResource extends JsonResource
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
            'attendance_id' => $this->attendance_id,
            'employee_id' => $this->employee_id,
            'mismatch_date' => $this->mismatch_date,
            'planned_work_mode' => $this->planned_work_mode,
            'actual_work_mode' => $this->actual_work_mode,
            'status' => $this->status,
            'acknowledged_by' => $this->acknowledged_by,
            'acknowledged_at' => $this->acknowledged_at,
            'escalated_at' => $this->escalated_at,
            'resolved_by' => $this->resolved_by,
            'resolved_at' => $this->resolved_at,
            'resolution_notes' => $this->resolution_notes,
            'attendance' => new AttendanceResource($this->whenLoaded('attendance')),
            'staff_member' => new StaffMemberProfileResource($this->whenLoaded('staffMember')),
            'acknowledged_by_employee' => new StaffMemberProfileResource($this->whenLoaded('acknowledgedBy')),
            'resolved_by_employee' => new StaffMemberProfileResource($this->whenLoaded('resolvedBy')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
