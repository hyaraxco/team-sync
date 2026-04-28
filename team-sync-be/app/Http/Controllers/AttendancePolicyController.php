<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Interfaces\AttendanceRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    /**
     * List all attendance policies (one per employment type).
     */
    public function index(): JsonResponse
    {
        try {
            $policies = $this->repository->getAttendancePolicies();

            return ResponseHelper::jsonResponse(true, 'Attendance Policies Retrieved Successfully', $policies, 200);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AttendancePolicyController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Update an attendance policy by ID.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'work_start_time' => 'sometimes|date_format:H:i:s',
            'work_end_time' => 'sometimes|date_format:H:i:s',
            'work_days_per_week' => 'sometimes|integer|min:1|max:7',
            'default_working_weekdays' => 'sometimes|array',
            'default_working_weekdays.*' => 'string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'late_grace_minutes' => 'sometimes|integer|min:0|max:120',
            'half_day_min_hours' => 'sometimes|numeric|min:0|max:12',
            'warning_absent_pct' => 'sometimes|numeric|min:0|max:100',
        ]);

        try {
            $policy = $this->repository->updateAttendancePolicy($id, $data);

            return ResponseHelper::jsonResponse(true, 'Attendance Policy Updated Successfully', $policy, 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Attendance Policy Not Found', null, 404);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AttendancePolicyController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }
}
