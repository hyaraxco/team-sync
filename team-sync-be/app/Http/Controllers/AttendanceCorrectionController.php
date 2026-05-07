<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\AttendanceCorrectionApproveRequest;
use App\Http\Requests\AttendanceCorrectionListRequest;
use App\Http\Requests\AttendanceCorrectionRejectRequest;
use App\Http\Requests\AttendanceCorrectionStoreRequest;
use App\Interfaces\AttendanceCorrectionRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Middleware\PermissionMiddleware;

class AttendanceCorrectionController extends Controller implements HasMiddleware
{
    private AttendanceCorrectionRepositoryInterface $repository;

    public function __construct(AttendanceCorrectionRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['attendance-correction-list']), only: ['getAllPaginated', 'show']),
            new Middleware(PermissionMiddleware::using(['attendance-correction-create']), only: ['store', 'getMyCorrections']),
            new Middleware(PermissionMiddleware::using(['attendance-correction-approve']), only: ['approve', 'reject']),
        ];
    }

    public function getAllPaginated(AttendanceCorrectionListRequest $request)
    {
        $data = $request->validated();

        try {
            $corrections = $this->repository->getAllPaginated(
                $data['search'] ?? null,
                (int) ($data['row_per_page'] ?? 10),
                $data['status'] ?? null
            );

            return ResponseHelper::jsonResponse(true, 'Attendance Corrections Retrieved Successfully', $corrections, 200);
        } catch (\Throwable $e) {
            Log::error('AttendanceCorrectionController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getMyCorrections()
    {
        try {
            $corrections = $this->repository->getMyCorrections();

            return ResponseHelper::jsonResponse(true, 'My Corrections Retrieved Successfully', $corrections, 200);
        } catch (\Throwable $e) {
            Log::error('AttendanceCorrectionController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function show(string $id)
    {
        try {
            $correction = $this->repository->getById($id);

            return ResponseHelper::jsonResponse(true, 'Correction Retrieved Successfully', $correction, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Correction Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('AttendanceCorrectionController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function store(AttendanceCorrectionStoreRequest $request)
    {
        $data = $request->validated();

        try {
            $correction = $this->repository->store($data);

            return ResponseHelper::jsonResponse(true, 'Correction Requested Successfully', $correction, 201);
        } catch (\Exception $e) {
            Log::warning('AttendanceCorrectionController domain exception: '.$e->getMessage());

            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            Log::error('AttendanceCorrectionController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function approve(AttendanceCorrectionApproveRequest $request, string $id)
    {
        $data = $request->validated();

        try {
            $correction = $this->repository->approve($id, $data);

            return ResponseHelper::jsonResponse(true, 'Correction Approved Successfully', $correction, 200);
        } catch (\Exception $e) {
            Log::warning('AttendanceCorrectionController domain exception: '.$e->getMessage());

            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            Log::error('AttendanceCorrectionController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function reject(AttendanceCorrectionRejectRequest $request, string $id)
    {
        $data = $request->validated();

        try {
            $correction = $this->repository->reject($id, $data);

            return ResponseHelper::jsonResponse(true, 'Correction Rejected Successfully', $correction, 200);
        } catch (\Exception $e) {
            Log::warning('AttendanceCorrectionController domain exception: '.$e->getMessage());

            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            Log::error('AttendanceCorrectionController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }
}
