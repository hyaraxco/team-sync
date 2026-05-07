<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LicenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'license_hash' => $this->license_hash,
            'company_name' => $this->company_name,
            'contact_email' => $this->contact_email,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'is_active' => $this->is_active,
            'features' => $this->features,
            'max_users' => $this->max_users,
            'current_users' => $this->current_users,
            'activated_at' => $this->activated_at?->toIso8601String(),
            'last_validated_at' => $this->last_validated_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
