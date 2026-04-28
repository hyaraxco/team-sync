<?php

namespace App\Interfaces;

interface LeaveEntitlementRepositoryInterface
{
    public function getAll(?string $employmentType = null);

    public function findOrFail(string $id);

    public function update(string $id, array $data);
}
