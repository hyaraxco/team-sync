<?php

namespace App\Interfaces;

interface HybridWorkScheduleRepositoryInterface
{
    public function getSchedulesPaginated(
        int $perPage,
        ?string $search = null,
        ?string $status = null
    );

    public function getScheduleByStaffMemberId(int $staffMemberId);

    public function getOverridesByStaffMemberIdPaginated(int $staffMemberId, int $perPage);

    public function getOverridesPaginated(
        int $perPage,
        ?string $search = null,
        ?string $status = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    );
}
