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
        return [
            'id' => $this->id,
            'user' => new UserResource($this->whenLoaded('user')),
            'code' => $this->code,
            'identity_number' => $this->identity_number,
            'npwp' => $this->npwp,
            'bpjs_ketenagakerjaan' => $this->bpjs_ketenagakerjaan,
            'bpjs_kesehatan' => $this->bpjs_kesehatan,
            'ptkp_status' => $this->ptkp_status,
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

            'job_information' => new JobInformationResource($this->whenLoaded('jobInformation')),
            'bank_information' => new BankInformationResource($this->whenLoaded('bankInformation')),
            'emergency_contacts' => EmergencyContactResource::collection($this->whenLoaded('emergencyContacts')),
            'team' => new TeamResource($this->whenLoaded('team')),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
