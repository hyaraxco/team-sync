<?php

namespace App\Interfaces;

interface AttendanceCorrectionRepositoryInterface
{
    public function getAllPaginated(?string $search, int $rowPerPage, ?string $status = null);

    public function getMyCorrections();

    public function getById(string $id);

    public function store(array $data);

    public function approve(string $id, array $data);

    public function reject(string $id, array $data);
}
