<?php

namespace App\Repositories;

use App\Interfaces\LeaveEntitlementRepositoryInterface;
use App\Models\LeaveEntitlement;

class LeaveEntitlementRepository implements LeaveEntitlementRepositoryInterface
{
    public function getAll(?string $employmentType = null)
    {
        $query = LeaveEntitlement::query()
            ->orderBy('employment_type')
            ->orderBy('leave_type');

        if ($employmentType !== null) {
            $query->where('employment_type', $employmentType);
        }

        return $query->get();
    }

    public function findOrFail(string $id)
    {
        return LeaveEntitlement::query()->findOrFail($id);
    }

    public function update(string $id, array $data)
    {
        $entitlement = LeaveEntitlement::query()->findOrFail($id);
        $entitlement->update($data);

        return $entitlement->fresh();
    }
}
