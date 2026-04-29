<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\PayslipResource;
use App\Interfaces\PayrollRepositoryInterface;
use App\Models\PayrollDetail;
use App\Services\EmailService;
use App\Services\PayslipPdfService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Throwable;

class PayslipController extends Controller implements HasMiddleware
{
    public function __construct(
        private PayslipPdfService $payslipPdfService,
        private EmailService $emailService,
        private PayrollRepositoryInterface $repository
    ) {}

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['payslip-view']), only: ['index', 'show', 'download', 'email']),
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

        $paginated = $this->repository->getMyPayslipsPaginated(
            (int) $staffMemberProfile->id,
            $validated['search'] ?? null,
            isset($validated['year']) ? (int) $validated['year'] : null,
            (int) ($validated['row_per_page'] ?? 12)
        );

        return ResponseHelper::jsonResponse(
            true,
            'Payslips Retrieved Successfully',
            PaginateResource::make($paginated, PayslipResource::class),
            200
        );
    }

    public function show(Request $request, string $id)
    {
        $payslip = $this->findOwnedPayslip($request, $id);

        return ResponseHelper::jsonResponse(true, 'Payslip Retrieved Successfully', new PayslipResource($payslip), 200);
    }

    public function download(Request $request, string $id)
    {
        $payslip = $this->findOwnedPayslip($request, $id);
        $pdf = $this->payslipPdfService->render($payslip);
        $filename = 'payslip-'.$payslip->getKey().'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function email(Request $request, string $id)
    {
        try {
            $payslip = $this->findOwnedPayslip($request, $id);
            $pdf = $this->payslipPdfService->render($payslip);

            $this->emailService->sendPayslipToEmployee($payslip, $pdf);

            return ResponseHelper::jsonResponse(true, 'Payslip emailed successfully', null, 200);
        } catch (Throwable $exception) {
            return ResponseHelper::jsonResponse(
                false,
                $exception->getMessage() ?: 'Failed to email payslip',
                null,
                422
            );
        }
    }

    private function findOwnedPayslip(Request $request, string $id): PayrollDetail
    {
        $staffMemberProfile = $request->user()?->staffMemberProfile;

        if (! $staffMemberProfile) {
            throw (new ModelNotFoundException)->setModel(PayrollDetail::class);
        }

        return $this->repository->findOwnedPaidPayslipOrFail($id, (int) $staffMemberProfile->id);
    }
}
