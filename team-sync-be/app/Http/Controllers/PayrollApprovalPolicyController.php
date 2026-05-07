<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\PayrollApprovalDecisionRequest;
use App\Http\Requests\PayrollApprovalPolicyStoreRequest;
use App\Http\Requests\PayrollApprovalPolicyUpdateRequest;
use App\Interfaces\PayrollRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
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
            Log::error('PayrollApprovalPolicyController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function store(PayrollApprovalPolicyStoreRequest $request)
    {
        $validated = $request->validated();

        try {
            $policy = $this->payrollRepository->createApprovalPolicy($validated);

            return ResponseHelper::jsonResponse(
                true,
                'Approval Policy Created Successfully',
                $policy,
                201
            );
        } catch (\Throwable $e) {
            Log::error('PayrollApprovalPolicyController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function update(PayrollApprovalPolicyUpdateRequest $request, int $id)
    {
        $validated = $request->validated();

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
            Log::error('PayrollApprovalPolicyController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

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
            Log::error('PayrollApprovalPolicyController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

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
            Log::error('PayrollApprovalPolicyController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function submitApproval(PayrollApprovalDecisionRequest $request, string $payrollId)
    {
        $validated = $request->validated();

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
            Log::warning('PayrollApprovalPolicyController domain exception: '.$e->getMessage());

            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            Log::error('PayrollApprovalPolicyController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }
}
