<?php

namespace App\Repositories;

use App\Interfaces\HybridWorkScheduleRepositoryInterface;
use App\Models\HybridScheduleOverride;
use App\Models\HybridWorkSchedule;

class HybridWorkScheduleRepository implements HybridWorkScheduleRepositoryInterface
{
    public function getSchedulesPaginated(
        int $perPage,
        ?string $search = null
    ) {
        $query = HybridWorkSchedule::with(['staffMember.user'])
            ->orderBy('created_at', 'desc');

        if ($search !== null && $search !== '') {
            $query->whereHas('staffMember.user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
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

    public function getOverridesPaginated(
        int $perPage,
        ?string $search = null,
        ?string $status = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ) {
        $query = HybridScheduleOverride::with(['staffMember.user', 'staffMember.hybridWorkSchedules'])
            ->orderBy('date', 'desc');

        if ($search !== null && $search !== '') {
            $query->whereHas('staffMember.user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        if ($dateFrom !== null) {
            $query->whereDate('date', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->whereDate('date', '<=', $dateTo);
        }

        return $query->paginate($perPage);
    }
}
