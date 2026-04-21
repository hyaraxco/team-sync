<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\PayslipResource;
use App\Models\PayrollAdjustment;
use App\Models\PayrollDetail;
use App\Services\PayslipPdfService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class PayslipController extends Controller implements HasMiddleware
{
    public function __construct(private PayslipPdfService $payslipPdfService)
    {
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['payslip-view']), only: ['index', 'show', 'download']),
        ];
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string',
            'year' => 'nullable|integer',
            'row_per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $staffMemberProfile = $request->user()?->staffMemberProfile;

        if (! $staffMemberProfile) {
            return ResponseHelper::jsonResponse(false, 'Employee Profile Not Found', null, 404);
        }

        $payslips = PayrollDetail::query()->select('payroll_details.*')->with([
            'payroll',
            'staffMember.user',
            'staffMember.jobInformation.team',
        ])
            ->join('payrolls', 'payrolls.id', '=', 'payroll_details.payroll_id')
            ->where('employee_id', $staffMemberProfile->id)
            ->where('payrolls.status', 'paid')
            ->when($validated['year'] ?? null, function ($query, $year) {
                $query->whereYear('payrolls.salary_month', $year);
            })
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->where('payrolls.salary_month', 'like', '%' . $search . '%');
            })
            ->orderByDesc('payrolls.salary_month');

        $paginated = $payslips->paginate($validated['row_per_page'] ?? 12);

        return ResponseHelper::jsonResponse(
            true,
            'Payslips Retrieved Successfully',
            PaginateResource::make($paginated, PayslipResource::class),
            200
        );
    }

    public function show(Request $request, string $id)
    {
        try {
            $payslip = $this->findOwnedPayslip($request, $id);

            return ResponseHelper::jsonResponse(true, 'Payslip Retrieved Successfully', new PayslipResource($payslip), 200);
        } catch (ModelNotFoundException) {
            return ResponseHelper::jsonResponse(false, 'Payslip Not Found', null, 404);
        }
    }

    public function download(Request $request, string $id)
    {
        try {
            $payslip = $this->findOwnedPayslip($request, $id);
            $pdf = $this->payslipPdfService->render($payslip);
            $filename = 'payslip-' . $payslip->getKey() . '.pdf';

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (ModelNotFoundException) {
            return ResponseHelper::jsonResponse(false, 'Payslip Not Found', null, 404);
        }
    }

    private function findOwnedPayslip(Request $request, string $id): PayrollDetail
    {
        $staffMemberProfile = $request->user()?->staffMemberProfile;

        if (! $staffMemberProfile) {
            throw (new ModelNotFoundException())->setModel(PayrollDetail::class);
        }

        $payslip = PayrollDetail::with([
            'payroll',
            'staffMember.user',
            'staffMember.jobInformation.team',
            'staffMember.bankInformation',
        ])
            ->where('id', $id)
            ->where('employee_id', $staffMemberProfile->id)
            ->whereHas('payroll', function ($query) {
                $query->where('status', 'paid');
            })
            ->firstOrFail();

        $targetPeriodId = $payslip->payroll?->attendance_period_id;
        $appliedAdjustments = collect();

        if ($targetPeriodId) {
            $appliedAdjustments = PayrollAdjustment::query()
                ->where('employee_id', $payslip->employee_id)
                ->where('target_period_id', $targetPeriodId)
                ->where('status', PayrollAdjustment::STATUS_APPLIED)
                ->orderBy('id')
                ->get();
        }

        $payslip->setRelation('appliedAdjustments', $appliedAdjustments);

        return $payslip;
    }
}
