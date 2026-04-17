<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\PaginateResource;
use App\Interfaces\AttendanceCorrectionRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
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

    public function getAllPaginated(Request $request)
    {
        $data = $request->validate([
            'search' => 'nullable|string',
            'row_per_page' => 'nullable|integer',
            'status' => 'nullable|string|in:pending,approved,rejected',
        ]);

        try {
            $corrections = $this->repository->getAllPaginated(
                $data['search'] ?? null,
                (int) ($data['row_per_page'] ?? 10),
                $data['status'] ?? null
            );

            return ResponseHelper::jsonResponse(true, 'Attendance Corrections Retrieved Successfully', $corrections, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function getMyCorrections()
    {
        try {
            $corrections = $this->repository->getMyCorrections();
            return ResponseHelper::jsonResponse(true, 'My Corrections Retrieved Successfully', $corrections, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
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
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'attendance_id' => 'required|exists:attendances,id',
            'requested_check_in' => 'nullable|date',
            'requested_check_out' => 'nullable|date',
            'reason' => 'required|string',
        ]);

        try {
            $correction = $this->repository->store($data);
            return ResponseHelper::jsonResponse(true, 'Correction Requested Successfully', $correction, 201);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function approve(Request $request, string $id)
    {
        $data = $request->validate([
            'review_notes' => 'nullable|string'
        ]);

        try {
            $correction = $this->repository->approve($id, $data);
            return ResponseHelper::jsonResponse(true, 'Correction Approved Successfully', $correction, 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function reject(Request $request, string $id)
    {
        $data = $request->validate([
            'review_notes' => 'required|string'
        ]);

        try {
            $correction = $this->repository->reject($id, $data);
            return ResponseHelper::jsonResponse(true, 'Correction Rejected Successfully', $correction, 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
