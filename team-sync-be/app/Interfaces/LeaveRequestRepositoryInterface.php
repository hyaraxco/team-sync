<?php

namespace App\Interfaces;

interface LeaveRequestRepositoryInterface
{
    public function getAll(
        ?string $search,
        ?int $limit,
        bool $execute
    );

    public function getAllPaginated(
        ?string $search,
        int $rowPerPage,
        ?string $status = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    );

    public function getById(
        string $id
    );

    public function getMyLeaveRequests();

    public function store(
        array $data
    );

    public function approve(
        string $id
    );

    public function reject(
        string $id
    );

    public function bulkAction(
        array $ids,
        string $action
    );

    public function uploadProof(
        string $id,
        array $data
    );

    public function reviewProof(
        string $id,
        array $data
    );

    public function getCalendarData(string $month);
}
