<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobInformationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isOwnProfile = $user && $user->staffMemberProfile && $user->staffMemberProfile->id === $this->staff_member_id;
        $canEdit = $user && $user->can('staff-member-edit');
        $canSeePayroll = $user && $user->can('payroll-list');
        $canSeeSensitive = $isOwnProfile || $canEdit || $canSeePayroll;

        return [
            'id' => $this->id,
            'job_title' => $this->job_title,
            'team' => new TeamResource($this->whenLoaded('team')),
            'status' => $this->status,
            'employment_type' => $this->employment_type,
            'work_location' => $this->work_location,
            'start_date' => $this->start_date,
            'monthly_salary' => $this->when($canSeeSensitive, $this->monthly_salary),
        ];
    }
}
