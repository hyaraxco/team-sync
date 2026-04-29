<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Interfaces\OptionRepositoryInterface;

class OptionController extends Controller
{
    private OptionRepositoryInterface $optionRepository;

    public function __construct(OptionRepositoryInterface $optionRepository)
    {
        $this->optionRepository = $optionRepository;
    }

    public function getDepartments()
    {
        try {
            $departments = $this->optionRepository->getDepartmentOptions();

            return ResponseHelper::jsonResponse(
                true,
                'Department options retrieved successfully',
                $departments,
                200
            );
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(
                false,
                'Failed to retrieve department options',
                null,
                500
            );
        }
    }

    public function getEmploymentTypes()
    {
        try {
            $employmentTypes = $this->optionRepository->getEmploymentTypeOptions();

            return ResponseHelper::jsonResponse(
                true,
                'Employment type options retrieved successfully',
                $employmentTypes,
                200
            );
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(
                false,
                'Failed to retrieve employment type options',
                null,
                500
            );
        }
    }

    public function getJobStatuses()
    {
        try {
            $jobStatuses = $this->optionRepository->getJobStatusOptions();

            return ResponseHelper::jsonResponse(
                true,
                'Job status options retrieved successfully',
                $jobStatuses,
                200
            );
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(
                false,
                'Failed to retrieve job status options',
                null,
                500
            );
        }
    }

    public function getTaskPriorities()
    {
        try {
            $taskPriorities = $this->optionRepository->getTaskPriorityOptions();

            return ResponseHelper::jsonResponse(
                true,
                'Task priority options retrieved successfully',
                $taskPriorities,
                200
            );
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(
                false,
                'Failed to retrieve task priority options',
                null,
                500
            );
        }
    }

    public function getTaskStatuses()
    {
        try {
            $taskStatuses = $this->optionRepository->getTaskStatusOptions();

            return ResponseHelper::jsonResponse(
                true,
                'Task status options retrieved successfully',
                $taskStatuses,
                200
            );
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(
                false,
                'Failed to retrieve task status options',
                null,
                500
            );
        }
    }

    public function getLeaveTypes()
    {
        try {
            $leaveTypes = $this->optionRepository->getLeaveTypeOptions();

            return ResponseHelper::jsonResponse(
                true,
                'Leave type options retrieved successfully',
                $leaveTypes,
                200
            );
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(
                false,
                'Failed to retrieve leave type options',
                null,
                500
            );
        }
    }

    public function getWorkLocations()
    {
        try {
            $workLocations = $this->optionRepository->getWorkLocationOptions();

            return ResponseHelper::jsonResponse(
                true,
                'Work location options retrieved successfully',
                $workLocations,
                200
            );
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(
                false,
                'Failed to retrieve work location options',
                null,
                500
            );
        }
    }

    public function getSkillLevels()
    {
        try {
            $skillLevels = $this->optionRepository->getSkillLevelOptions();

            return ResponseHelper::jsonResponse(
                true,
                'Skill level options retrieved successfully',
                $skillLevels,
                200
            );
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(
                false,
                'Failed to retrieve skill level options',
                null,
                500
            );
        }
    }

    public function getReligions()
    {
        try {
            $religions = $this->optionRepository->getReligionOptions();

            return ResponseHelper::jsonResponse(
                true,
                'Religion options retrieved successfully',
                $religions,
                200
            );
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(
                false,
                'Failed to retrieve religion options',
                null,
                500
            );
        }
    }

    public function getMaritalStatuses()
    {
        try {
            $maritalStatuses = $this->optionRepository->getMaritalStatusOptions();

            return ResponseHelper::jsonResponse(
                true,
                'Marital status options retrieved successfully',
                $maritalStatuses,
                200
            );
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(
                false,
                'Failed to retrieve marital status options',
                null,
                500
            );
        }
    }

    public function getBloodTypes()
    {
        try {
            $bloodTypes = $this->optionRepository->getBloodTypeOptions();

            return ResponseHelper::jsonResponse(
                true,
                'Blood type options retrieved successfully',
                $bloodTypes,
                200
            );
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(
                false,
                'Failed to retrieve blood type options',
                null,
                500
            );
        }
    }

    public function getPtkpStatuses()
    {
        try {
            $ptkpStatuses = $this->optionRepository->getPtkpStatusOptions();

            return ResponseHelper::jsonResponse(
                true,
                'PTKP status options retrieved successfully',
                $ptkpStatuses,
                200
            );
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(
                false,
                'Failed to retrieve PTKP status options',
                null,
                500
            );
        }
    }

    public function getProjectTaskTemplates()
    {
        try {
            $templates = $this->optionRepository->getProjectTaskTemplateOptions();

            return ResponseHelper::jsonResponse(
                true,
                'Project task template options retrieved successfully',
                $templates,
                200
            );
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(
                false,
                'Failed to retrieve project task template options',
                null,
                500
            );
        }
    }
}
