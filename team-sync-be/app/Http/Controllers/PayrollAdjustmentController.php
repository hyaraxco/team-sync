<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\PayrollAdjustment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class PayrollAdjustmentController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['payroll-menu'])),
        ];
    }

    /**
     * List all payroll adjustments with optional filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PayrollAdjustment::with(['staffMember', 'sourcePeriod', 'targetPeriod', 'approver'])
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('target_period_id')) {
            $query->where('target_period_id', $request->target_period_id);
        }

        if ($request->has('staff_member_id')) {
            $query->where('staff_member_id', $request->staff_member_id);
        }

        $adjustments = $query->paginate($request->get('per_page', 15));

        return ResponseHelper::jsonResponse(true, 'Payroll Adjustments Retrieved Successfully', $adjustments, 200);
    }

    /**
     * Approve a pending adjustment.
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $adjustment = PayrollAdjustment::findOrFail($id);

        if ($adjustment->status !== PayrollAdjustment::STATUS_PENDING) {
            return ResponseHelper::jsonResponse(false, 'Only pending adjustments can be approved', null, 400);
        }

        $adjustment->update([
            'status' => PayrollAdjustment::STATUS_APPROVED,
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
        ]);

        return ResponseHelper::jsonResponse(
            true,
            'Payroll Adjustment Approved Successfully',
            $adjustment->fresh(['staffMember', 'sourcePeriod', 'targetPeriod', 'approver']),
            200
        );
    }
}
