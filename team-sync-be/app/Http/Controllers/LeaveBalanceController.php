<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\Attendance\LeaveBalanceService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class LeaveBalanceController extends Controller implements HasMiddleware
{
    private LeaveBalanceService $leaveBalanceService;

    public function __construct(LeaveBalanceService $leaveBalanceService)
    {
        $this->leaveBalanceService = $leaveBalanceService;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['leave-request-my-requests'])),
        ];
    }

    public function getMyBalances(Request $request)
    {
        try {
            $employee = $request->user()->staffMemberProfile;
            if (! $employee) {
                return ResponseHelper::jsonResponse(false, 'Employee profile not found.', [], 404);
            }

            $balances = $this->leaveBalanceService->getEmployeeBalances($employee->id);

            return ResponseHelper::jsonResponse(true, 'Leave balances retrieved successfully.', $balances, 200);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('LeaveBalanceController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }
}
