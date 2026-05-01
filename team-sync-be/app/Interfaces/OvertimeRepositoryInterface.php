<?php

namespace App\Interfaces;

use App\Models\OvertimeRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OvertimeRepositoryInterface
{
    public function getAllPaginated(
        ?string $status,
        ?int $staffMemberId,
        ?string $overtimeType,
        ?string $dateFrom,
        ?string $dateTo,
        int $perPage = 15
    ): LengthAwarePaginator;

    public function getById(int $id): OvertimeRecord;

    public function getByStaffMember(int $staffMemberId, ?string $status, int $perPage = 15): LengthAwarePaginator;

    public function create(array $data): OvertimeRecord;

    public function approve(OvertimeRecord $record, int $approvedBy): OvertimeRecord;

    public function reject(OvertimeRecord $record, string $reason): OvertimeRecord;

    public function getSummary(): array;

    public function getWeeklyHoursForStaffMember(int $staffMemberId, string $date): float;
}
