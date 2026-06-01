<?php

namespace App\Http\Resources;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Calculate progress based on tasks completion
        $progress = 0;
        if ($this->relationLoaded('tasks') && $this->tasks->count() > 0) {
            $completedTasks = $this->tasks->where('status', 'done')->count();
            $progress = round(($completedTasks / $this->tasks->count()) * 100);
        }

        $user = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'priority' => $this->priority,
            'status' => $this->status,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'description' => $this->description,
            'photo' => $this->photo ? asset('storage/'.$this->photo) : null,
            'budget' => (float) (string) $this->budget,
            'progress' => $progress,
            'leader' => new StaffMemberProfileResource($this->projectLeader),
            'teams' => TeamResource::collection($this->whenLoaded('teams')),
            'is_project_leader' => $this->resolveIsProjectLeader($user),
            'can_create_task' => $this->when(request()->routeIs('projects.show'), fn () => $this->resolveCanCreateTask($user)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function resolveIsProjectLeader(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $profileId = $user->staffMemberProfile?->id;
        if (! $profileId) {
            return false;
        }

        return (int) $this->project_leader_id === (int) $profileId;
    }

    /**
     * Delegate to ProjectTaskPolicy::create so authorization stays single-source.
     * Returning false is safe when the user is unauthenticated.
     */
    private function resolveCanCreateTask(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return Gate::forUser($user)->allows('create', [
            ProjectTask::class,
            ['project_id' => $this->id],
        ]);
    }
}
