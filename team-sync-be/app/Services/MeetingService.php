<?php

namespace App\Services;

use App\Interfaces\MeetingRepositoryInterface;
use App\Jobs\BroadcastMeetingJob;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class MeetingService
{
    public function __construct(
        private readonly MeetingRepositoryInterface $meetingRepository
    ) {}

    public function getAllPaginated(
        ?string $search,
        ?string $department,
        ?int $rowPerPage
    ): LengthAwarePaginator {
        return $this->meetingRepository->getAllPaginated($search, $department, $rowPerPage);
    }

    public function getUpcoming(?int $limit = 10): Collection
    {
        return $this->meetingRepository->getUpcoming($limit);
    }

    public function getById(int $id): Meeting
    {
        return $this->meetingRepository->getById($id);
    }

    public function create(array $data, User $creator): Meeting
    {
        $data['created_by'] = $creator->id;

        $meeting = $this->meetingRepository->create($data);

        BroadcastMeetingJob::dispatch((int) $meeting->id, 'scheduled')->onQueue('meetings');

        return $meeting;
    }

    public function getNeedingReminder(): Collection
    {
        return $this->meetingRepository->getNeedingReminder();
    }

    public function resolveRecipients(Meeting $meeting): Collection
    {
        $meeting->loadMissing(['teams.members.staffMember.user', 'creator']);

        $teamUsers = $meeting->teams
            ->flatMap(fn ($team) => $team->members)
            ->map(fn ($member) => $member->staffMember?->user)
            ->filter();

        $departments = is_array($meeting->departments) ? $meeting->departments : [];

        $departmentUsers = User::query()
            ->when(! empty($departments), function ($query) use ($departments) {
                $query->whereHas('staffMemberProfile.jobInformation.team', function ($teamQuery) use ($departments) {
                    $teamQuery->whereIn('department', $departments);
                });
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->get();

        return $teamUsers
            ->merge($departmentUsers)
            ->unique('id')
            ->reject(fn ($user) => $user->id === $meeting->created_by)
            ->values();
    }
}
