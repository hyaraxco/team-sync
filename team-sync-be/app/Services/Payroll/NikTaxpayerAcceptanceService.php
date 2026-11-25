<?php

namespace App\Services\Payroll;

use App\DTOs\NikTaxpayerAcceptanceResultDto;
use App\Exceptions\PayrollStateException;
use App\Interfaces\StaffMemberProfileRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class NikTaxpayerAcceptanceService
{
    public function __construct(
        private StaffMemberProfileRepositoryInterface $repository,
    ) {}

    /**
     * Accept an employee's current NIK as a valid taxpayer identity.
     *
     * Runs invariant checks and the write under a single transaction with
     * a row lock to prevent TOCTOU races with concurrent profile edits.
     *
     * @throws PayrollStateException if the employee has no identity on file
     * @throws ModelNotFoundException if employee not found
     */
    public function accept(string $employeeId, int $actorUserId): NikTaxpayerAcceptanceResultDto
    {
        return DB::transaction(function () use ($employeeId, $actorUserId) {
            $employee = $this->repository->getByIdForUpdate($employeeId);

            if (! $employee->identity_number_hash) {
                throw new PayrollStateException('Employee has no identity number on file.');
            }

            if ($employee->nik_taxpayer_accepted_hash !== $employee->identity_number_hash) {
                $acceptedAt = now();
                $this->repository->persistNikTaxpayerAcceptance(
                    $employee,
                    $employee->identity_number_hash,
                    $acceptedAt,
                    $actorUserId,
                );
                $employee->refresh();
            }

            return new NikTaxpayerAcceptanceResultDto(
                accepted_at: $employee->nik_taxpayer_accepted_at,
            );
        });
    }
}
