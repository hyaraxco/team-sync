<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Interfaces\PayrollRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class PayrollApprovalPolicyController extends Controller implements HasMiddleware
{
    private PayrollRepositoryInterface $payrollRepository;

    public function __construct(PayrollRepositoryInterface $payrollRepository)
    {
        $this->payrollRepository = $payrollRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['payroll-statistics']), only: ['index']),
            new Middleware(PermissionMiddleware::using(['payroll-edit']), only: ['store', 'update', 'destroy']),
            new Middleware(PermissionMiddleware::using(['payroll-list']), only: ['getApprovalStatus']),
            new Middleware(PermissionMiddleware::using(['payroll-edit']), only: ['submitApproval']),
        ];
    }

    public function index()
    {
        try {
            $policies = $this->payrollRepository->getApprovalPolicies();

            return ResponseHelper::jsonResponse(
                true,
                'Approval Policies Retrieved Successfully',
                $policies,
                200
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('PayrollApprovalPolicyController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0',
            'required_role' => 'required|string|max:100',
            'approval_order' => 'required|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $policy = $this->payrollRepository->createApprovalPolicy($validated);

            return ResponseHelper::jsonResponse(
                true,
                'Approval Policy Created Successfully',
                $policy,
                201
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('PayrollApprovalPolicyController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'min_amount' => 'sometimes|required|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0',
            'required_role' => 'sometimes|required|string|max:100',
            'approval_order' => 'sometimes|required|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $policy = $this->payrollRepository->updateApprovalPolicy($id, $validated);

            return ResponseHelper::jsonResponse(
                true,
                'Approval Policy Updated Successfully',
                $policy,
                200
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Approval Policy Not Found', null, 404);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('PayrollApprovalPolicyController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function destroy(int $id)
    {
        try {
            $this->payrollRepository->deleteApprovalPolicy($id);

            return ResponseHelper::jsonResponse(
                true,
                'Approval Policy Deleted Successfully',
                null,
                200
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Approval Policy Not Found', null, 404);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('PayrollApprovalPolicyController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getApprovalStatus(string $payrollId)
    {
        try {
            $status = $this->payrollRepository->getApprovalStatus($payrollId);

            return ResponseHelper::jsonResponse(
                true,
                'Approval Status Retrieved Successfully',
                $status,
                200
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payroll Not Found', null, 404);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('PayrollApprovalPolicyController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function submitApproval(Request $request, string $payrollId)
    {
        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $result = $this->payrollRepository->submitApprovalDecision(
                $payrollId,
                $validated,
                $request->user()?->id
            );

            return ResponseHelper::jsonResponse(
                true,
                'Approval Decision Submitted Successfully',
                $result,
                200
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payroll Not Found', null, 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('PayrollApprovalPolicyController domain exception: ' . $e->getMessage());
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('PayrollApprovalPolicyController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }
}
