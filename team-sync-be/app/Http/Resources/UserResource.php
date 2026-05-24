<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'profile_photo' => $this->profile_photo ? asset('storage/'.$this->profile_photo) : null,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'has_verified_email' => method_exists($this->resource, 'hasVerifiedEmail') ? $this->hasVerifiedEmail() : false,
            'employee_profile' => new StaffMemberProfileResource($this->whenLoaded('staffMemberProfile')),
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->pluck('name');
            }),
            'permissions' => $this->whenLoaded('permissions', function () {
                return $this->getAllPermissions()->pluck('name');
            }),
            'company_timezone' => $this->staffMemberProfile?->company?->timezone ?? 'Asia/Jakarta',
            'token' => $this->when(isset($this->token), $this->token),
            'created_at' => $this->created_at,
        ];
    }
}
