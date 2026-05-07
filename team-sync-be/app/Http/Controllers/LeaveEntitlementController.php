<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\LeaveEntitlementUpdateRequest;
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

    public function index(Request $request): JsonResponse
    {
        $entitlements = $this->repository->getAll($request->has('employment_type') ? $request->employment_type : null);

        // Group by employment_type for easier FE consumption
        $grouped = $entitlements->groupBy('employment_type');

        return ResponseHelper::jsonResponse(true, 'Leave Entitlements Retrieved Successfully', [
            'items' => $entitlements,
            'grouped' => $grouped,
        ], 200);
    }

    public function update(LeaveEntitlementUpdateRequest $request, string $id): JsonResponse
    {
        $data = $request->validated();

        $entitlement = $this->repository->update($id, $data);

        return ResponseHelper::jsonResponse(true, 'Leave Entitlement Updated Successfully', $entitlement, 200);
    }
}
