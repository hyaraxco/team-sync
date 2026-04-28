<?php

namespace App\Interfaces;

interface HybridWorkScheduleRepositoryInterface
{
    public function getSchedulesPaginated(int $perPage);

    public function getScheduleByStaffMemberId(int $staffMemberId);

    public function getOverridesByStaffMemberIdPaginated(int $staffMemberId, int $perPage);
}
