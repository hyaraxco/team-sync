<?php

namespace App\Interfaces;

use App\Models\StaffMemberProfile;

interface StaffMemberProfileRepositoryInterface
{
    public function getAll(
        ?string $search,
        ?string $status,
        ?string $type,
        ?string $workLocation,
        ?string $projectId,
        ?int $limit,
        bool $execute
    );

    public function getAllPaginated(
        ?string $search,
        ?string $status,
        ?string $type,
        ?string $workLocation,
        ?string $projectId,
        int $rowPerPage
    );

    public function getById(
        string $id
    );

    public function getMyProfile();

    public function create(
        array $data
    );

    public function update(
        string $id,
        array $data
    );

    public function delete(
        string $id
    );

    public function getStatistics();

    public function getPerformanceStatistics(string $employeeId);

    public function getMyTeam();

    public function getMyTeamMembers();

    public function getMyTeamProjects();

    public function emailExists(
        string $email
    ): bool;

    public function identityNumberExists(
        string $identityNumber
    ): bool;

    public function getByIdForUpdate(string $id): StaffMemberProfile;

    public function persistNikTaxpayerAcceptance(
        StaffMemberProfile $employee,
        string $acceptedHash,
        \DateTimeInterface $acceptedAt,
        int $actorUserId,
    ): void;
}
