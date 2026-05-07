<?php

namespace App\Repositories;

use App\Interfaces\MeetingRepositoryInterface;
use App\Models\Meeting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class MeetingRepository implements MeetingRepositoryInterface
{
    public function getAllPaginated(
        ?string $search,
        ?string $department,
        ?int $rowPerPage,
        ?array $teamIds = null
    ): LengthAwarePaginator {
        $query = Meeting::query()
            ->with(['creator', 'teams'])
            ->when($search, function ($query) use ($search) {
                $query->where('title', 'like', '%'.$search.'%');
            })
            ->when($department, function ($query) use ($department) {
                $query->whereJsonContains('departments', $department);
            })
            ->when($teamIds !== null, function ($query) use ($teamIds) {
                $query->whereHas('teams', function ($q) use ($teamIds) {
                    $q->whereIn('teams.id', $teamIds);
                });
            })
            ->orderByDesc('scheduled_at');

        return $query->paginate($rowPerPage ?? 10);
    }

    public function getUpcoming(?int $limit = 10, ?array $teamIds = null): Collection
    {
        return Meeting::query()
            ->upcoming()
            ->with(['creator', 'teams'])
            ->when($teamIds !== null, function ($query) use ($teamIds) {
                $query->whereHas('teams', function ($q) use ($teamIds) {
                    $q->whereIn('teams.id', $teamIds);
                });
            })
            ->limit($limit ?? 10)
            ->get();
    }

    public function getById(int $id): Meeting
    {
        return Meeting::query()
            ->with(['creator', 'teams'])
            ->findOrFail($id);
    }

    public function create(array $data): Meeting
    {
        return DB::transaction(function () use ($data) {
            $meeting = Meeting::create($data);

            if (! empty($data['team_ids']) && is_array($data['team_ids'])) {
                $meeting->teams()->sync($data['team_ids']);
            }

            return $meeting->load(['creator', 'teams']);
        });
    }

    public function getNeedingReminder(): Collection
    {
        return Meeting::query()
            ->needsReminder()
            ->with(['teams.members.staffMember.user', 'creator'])
            ->get();
    }
}
