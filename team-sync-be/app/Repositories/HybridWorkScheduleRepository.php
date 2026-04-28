<?php

namespace App\Repositories;

use App\Interfaces\HybridWorkScheduleRepositoryInterface;
use App\Models\HybridScheduleOverride;
use App\Models\HybridWorkSchedule;

class HybridWorkScheduleRepository implements HybridWorkScheduleRepositoryInterface
{
    public function getSchedulesPaginated(int $perPage)
    {
        return HybridWorkSchedule::query()->paginate($perPage);
    }

    public function getScheduleByStaffMemberId(int $staffMemberId)
    {
        return HybridWorkSchedule::query()
            ->where('staff_member_id', $staffMemberId)
            ->first();
    }

    public function getOverridesByStaffMemberIdPaginated(int $staffMemberId, int $perPage)
    {
        return HybridScheduleOverride::query()
            ->where('staff_member_id', $staffMemberId)
            ->orderBy('date', 'desc')
            ->paginate($perPage);
    }
}
