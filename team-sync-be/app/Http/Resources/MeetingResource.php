<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'scheduled_at' => $this->scheduled_at,
            'duration_minutes' => $this->duration_minutes,
            'location' => $this->location,
            'departments' => $this->departments,
            'creator' => new UserResource($this->whenLoaded('creator')),
            'teams' => TeamResource::collection($this->whenLoaded('teams')),
            'created_at' => $this->created_at,
        ];
    }
}
