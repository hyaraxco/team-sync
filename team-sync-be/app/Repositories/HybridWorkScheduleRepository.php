<?php

namespace App\Repositories;

use App\Interfaces\HybridWorkScheduleRepositoryInterface;
use App\Models\HybridScheduleOverride;
use App\Models\HybridWorkSchedule;

class HybridWorkScheduleRepository implements HybridWorkScheduleRepositoryInterface
{
    public function getSchedulesPaginated(
        int $perPage,
        ?string $search = null,
        ?string $status = null
    ) {
        $query = HybridWorkSchedule::with(['staffMember.user'])
            ->orderBy('created_at', 'desc');

        if ($search !== null && $search !== '') {
            $query->whereHas('staffMember.user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        if ($status !== null && $status !== '') {
            $query->whereHas('overrides', function ($q) use ($status) {
                $q->where('status', $status);
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
}
