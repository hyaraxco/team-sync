<?php

namespace App\Interfaces;

use App\Models\ThrPayroll;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ThrPayrollRepositoryInterface
{
    public function getAllPaginated(?int $year, ?string $status, int $perPage = 15): LengthAwarePaginator;

    public function getById(int $id): ThrPayroll;

    public function getByYearAndEvent(int $year, string $religionEvent): ?ThrPayroll;

    public function create(array $data): ThrPayroll;

    public function updateStatus(ThrPayroll $thrPayroll, string $status, array $extra = []): ThrPayroll;

    public function updateTotals(ThrPayroll $thrPayroll): ThrPayroll;

    public function getDetails(int $thrPayrollId, int $perPage = 15): LengthAwarePaginator;

    public function bulkCreateDetails(int $thrPayrollId, array $details): int;

    public function getYearSummary(int $year): array;
}
