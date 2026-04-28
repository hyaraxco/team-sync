<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Interfaces\LeaveEntitlementRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class LeaveEntitlementController extends Controller implements HasMiddleware
{
    public function __construct(private LeaveEntitlementRepositoryInterface $repository) {}

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['attendance-menu']), only: ['index', 'update']),
        ];
    }

    /**
     * List all leave entitlements, optionally filtered by employment_type.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $entitlements = $this->repository->getAll($request->has('employment_type') ? $request->employment_type : null);

            // Group by employment_type for easier FE consumption
            $grouped = $entitlements->groupBy('employment_type');

            return ResponseHelper::jsonResponse(true, 'Leave Entitlements Retrieved Successfully', [
                'items' => $entitlements,
                'grouped' => $grouped,
            ], 200);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('LeaveEntitlementController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Update a leave entitlement by ID.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'is_eligible' => 'sometimes|boolean',
            'is_paid' => 'sometimes|boolean',
            'quota_scope' => 'sometimes|nullable|string|in:annual,per_occurrence,unlimited,unpaid',
            'quota_days' => 'sometimes|nullable|numeric|min:0',
            'carry_over_max_days' => 'sometimes|nullable|integer|min:0',
            'requires_attachment' => 'sometimes|boolean',
            'requires_reason' => 'sometimes|boolean',
            'allowed_mime_types' => 'sometimes|nullable|array',
            'allowed_mime_types.*' => 'string',
            'max_attachment_size_kb' => 'sometimes|nullable|integer|min:0',
        ]);

        try {
            $entitlement = $this->repository->update($id, $data);

            return ResponseHelper::jsonResponse(true, 'Leave Entitlement Updated Successfully', $entitlement, 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Leave Entitlement Not Found', null, 404);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('LeaveEntitlementController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }
}
