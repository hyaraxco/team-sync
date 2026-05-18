<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OvertimeRecordResource extends JsonResource
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
            'staff_member_id' => $this->staff_member_id,
            'attendance_id' => $this->attendance_id,
            'date' => $this->date?->format('Y-m-d'),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'hours' => (float) $this->hours,
            'overtime_type' => $this->overtime_type,
            'status' => $this->status,
            'notes' => $this->notes,
            'rejection_reason' => $this->rejection_reason,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at,
            'staff_member' => $this->whenLoaded('staffMember', function () {
                return [
                    'id' => $this->staffMember->id,
                    'full_name' => $this->staffMember->full_name,
                    'code' => $this->staffMember->code,
                    'user' => $this->staffMember->relationLoaded('user') ? [
                        'id' => $this->staffMember->user?->id,
                        'name' => $this->staffMember->user?->name,
                        'email' => $this->staffMember->user?->email,
                        'profile_photo' => $this->staffMember->user?->profile_photo,
                    ] : null,
                ];
            }),
            'approved_by_user' => $this->whenLoaded('approvedByUser', function () {
                return [
                    'id' => $this->approvedByUser->id,
                    'name' => $this->approvedByUser->name,
                ];
            }),
            'attendance' => $this->whenLoaded('attendance', function () {
                return [
                    'id' => $this->attendance->id,
                    'check_in' => $this->attendance->check_in,
                    'check_out' => $this->attendance->check_out,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
