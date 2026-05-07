<?php

namespace App\Interfaces;

use App\Models\Meeting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface MeetingRepositoryInterface
{
    public function getAllPaginated(
        ?string $search,
        ?string $department,
        ?int $rowPerPage,
        ?array $teamIds = null
    ): LengthAwarePaginator;

    public function getUpcoming(?int $limit = 10, ?array $teamIds = null): Collection;

    public function getById(int $id): Meeting;

    public function create(array $data): Meeting;

    public function getNeedingReminder(): Collection;
}
