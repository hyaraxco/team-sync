<?php

namespace App\Interfaces;

interface AttendanceRepositoryInterface
{
    public function getAll(
        ?string $search,
        ?string $date,
        ?int $limit,
        bool $execute
    );

    public function getAllPaginated(
        ?string $search,
        int $rowPerPage
    );

    public function getMyAttendances(?string $from = null, ?string $to = null);

    public function getMyAttendanceStatistics();

    public function getById(string $id);

    public function getLastAttendanceByEmployee();

    public function checkIn(array $data);

    public function checkOut(array $data);

    public function getStatistics();

    public function acknowledgePolicyMismatch(string $id, array $data);

    public function resolvePolicyMismatch(string $id, array $data);

    public function getEmployeeStatistics(string $employeeId, array $filters);

    public function getAttendancePeriodsPaginated(int $perPage);

    public function hasOpenAttendancePeriod(): bool;

    public function createAttendancePeriod(array $data);

    public function findAttendancePeriodOrFail(string $id);

    public function updateAttendancePeriod(string $id, array $data);

    public function getAttendancePolicies();

    public function findAttendancePolicyOrFail(string $id);

    public function updateAttendancePolicy(string $id, array $data);
}
