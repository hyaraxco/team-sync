<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\AttendancePolicyUpdateRequest;
use App\Interfaces\AttendanceRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class AttendancePolicyController extends Controller implements HasMiddleware
{
    public function __construct(private AttendanceRepositoryInterface $repository) {}

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['attendance-menu']), only: ['index', 'update']),
        ];
    }

    public function index(): JsonResponse
    {
        $policies = $this->repository->getAttendancePolicies();

        return ResponseHelper::jsonResponse(true, 'Attendance Policies Retrieved Successfully', $policies, 200);
    }

    public function update(AttendancePolicyUpdateRequest $request, string $id): JsonResponse
    {
        $data = $request->validated();

        $policy = $this->repository->updateAttendancePolicy($id, $data);

        return ResponseHelper::jsonResponse(true, 'Attendance Policy Updated Successfully', $policy, 200);
    }
}
