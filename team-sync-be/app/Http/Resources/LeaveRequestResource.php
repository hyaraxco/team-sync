<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
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
            'employee_id' => $this->employee_id,
            'leave_type' => $this->leave_type,
            'type' => $this->leave_type, // alias for frontend compatibility
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'total_days' => $this->total_days,
            'days' => $this->total_days, // alias for frontend compatibility
            'reason' => $this->reason,
            'emergency_contact' => $this->emergency_contact,
            'proof_file_path' => $this->proof_file_path,
            'proof_file_name' => $this->proof_file_name,
            'proof_mime_type' => $this->proof_mime_type,
            'proof_size_kb' => $this->proof_size_kb,
            'proof_uploaded_at' => $this->proof_uploaded_at,
            'proof_review_status' => $this->proof_review_status,
            'proof_reviewed_by' => $this->proof_reviewed_by,
            'proof_reviewed_at' => $this->proof_reviewed_at,
            'proof_review_notes' => $this->proof_review_notes,
            'status' => $this->status,
            'approved_by' => $this->approved_by,
            'staff_member' => new StaffMemberProfileResource($this->whenLoaded('staffMember')),
            'approver' => new StaffMemberProfileResource($this->whenLoaded('approver')),
            'proof_reviewer' => new StaffMemberProfileResource($this->whenLoaded('proofReviewedBy')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
