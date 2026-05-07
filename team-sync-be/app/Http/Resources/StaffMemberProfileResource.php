<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffMemberProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isOwnProfile = $user && $user->staffMemberProfile && $user->staffMemberProfile->id === $this->id;
        $canEdit = $user && $user->can('staff-member-edit');
        $canSeeSensitive = $isOwnProfile || $canEdit;

        return [
            'id' => $this->id,
            'user' => new UserResource($this->whenLoaded('user')),
            'code' => $this->code,
            'identity_number' => $this->when($canSeeSensitive, $this->identity_number),
            'npwp' => $this->when($canSeeSensitive, $this->npwp),
            'bpjs_ketenagakerjaan' => $this->when($canSeeSensitive, $this->bpjs_ketenagakerjaan),
            'bpjs_kesehatan' => $this->when($canSeeSensitive, $this->bpjs_kesehatan),
            'ptkp_status' => $this->when($canSeeSensitive, $this->ptkp_status),
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'gender' => $this->gender,
            'religion' => $this->religion,
            'marital_status' => $this->marital_status,
            'blood_type' => $this->blood_type,
            'place_of_birth' => $this->place_of_birth,
            'address' => $this->address,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'last_education' => $this->last_education,
            'seniority_level' => $this->seniority_level,

            'job_information' => new JobInformationResource($this->whenLoaded('jobInformation')),
            'bank_information' => $this->when($canSeeSensitive, new BankInformationResource($this->whenLoaded('bankInformation'))),
            'emergency_contacts' => $this->when($canSeeSensitive, EmergencyContactResource::collection($this->whenLoaded('emergencyContacts'))),
            'team' => new TeamResource($this->whenLoaded('team')),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
