<?php

namespace App\Http\Controllers;

use App\Enums\PayrollStatus;
use App\Exceptions\ConcurrentModificationException;
use App\Exceptions\PayrollAlreadyPaidException;
use App\Exceptions\PayrollReconciliationBlockedException;
use App\Exceptions\PayrollStateException;
use App\Exports\PayrollExport;
use App\Exports\PayrollReportExport;
use App\Helpers\ResponseHelper;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\PayrollActivityLogResource;
use App\Http\Resources\PayrollDetailResource;
use App\Http\Resources\PayrollResource;
use App\Interfaces\PayrollRepositoryInterface;
use App\Jobs\GeneratePayrollJob;
use App\Models\Payroll;
use App\Services\PayrollActivityLogger;
use App\Services\PayslipPdfService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Requests\Payroll\PayrollDetailsRequest;
use App\Http\Requests\Payroll\PayrollGenerateRequest;
use App\Http\Requests\Payroll\PayrollListRequest;
use App\Http\Requests\Payroll\PayrollReconciliationRequest;
use App\Http\Requests\Payroll\PayrollSalaryMonthRequest;
use App\Http\Requests\ResolveReconciliationExceptionRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Middleware\PermissionMiddleware;
use ZipArchive;

/**
 * PayrollController — Handles payroll CRUD, generation, approval, and export operations.
 *
 * Access Control (Permission-Based):
 *   - payroll-list:        index, getAllPaginated, show, getDetails, getReconciliation,
 *                          getReconciliationResolutions, exportExcel, exportPdf, exportReport,
 *                          getActivityLogs
 *   - payroll-create:      generate, generateReadiness
 *   - payroll-create OR payroll-readiness-view: readinessDashboard, readinessTeamSummary
 *   - payroll-edit:        updateDetail, approvePayroll
 *   - payroll-process:     markAsPaid, reopenPayroll, resendNotifications,
 *                          getNotificationDeliveries, resolveReconciliationException
 *   - payroll-statistics:  getStatistics, getAnalytics, getComparison, getPayrollStatistics
 *
 * Why permission-only (no role-based scoping):
 *   In a single-tenant deployment, permission assignment IS the access control mechanism.
 *   Finance role receives all payroll permissions; HR receives only payroll-readiness-view;
 *   other roles receive none. This is enforced by Spatie middleware — no additional
 *   data-scoping layer is needed until multi-tenant is introduced.
 *
 * Multi-Tenant Note:
 *   When multi-tenancy is added, this controller will need tenant-scoped queries
 *   (e.g., via a TenantScope middleware or global scope on Payroll model) to prevent
 *   cross-tenant data leakage. Permission checks alone will not suffice.
 */
class PayrollController extends Controller implements HasMiddleware
{
    private PayrollRepositoryInterface $payrollRepository;

    private PayrollActivityLogger $activityLogger;

    public function __construct(
        PayrollRepositoryInterface $payrollRepository,
        PayrollActivityLogger $activityLogger,
        private readonly PayslipPdfService $payslipPdfService
    ) {
        $this->payrollRepository = $payrollRepository;
        $this->activityLogger = $activityLogger;
    }

    /**
     * Register route-level permission middleware.
     *
     * Each action group maps to a specific payroll permission defined in PermissionSeeder.
     * Middleware is applied via Spatie's PermissionMiddleware using the 'sanctum' guard.
     */
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['payroll-list']), only: ['index', 'getAllPaginated', 'show', 'getDetails', 'getReconciliation', 'getReconciliationResolutions', 'exportExcel', 'exportPdf', 'exportReport', 'getActivityLogs']),
            new Middleware(PermissionMiddleware::using(['payroll-create']), only: ['generate', 'generateReadiness']),
            new Middleware(PermissionMiddleware::using('payroll-create|payroll-readiness-view'), only: ['readinessDashboard', 'readinessTeamSummary']),
            new Middleware(PermissionMiddleware::using(['payroll-edit']), only: ['updateDetail', 'approvePayroll']),
            new Middleware(PermissionMiddleware::using(['payroll-process']), only: ['markAsPaid', 'reopenPayroll', 'resendNotifications', 'getNotificationDeliveries', 'resolveReconciliationException']),
            new Middleware(PermissionMiddleware::using(['payroll-statistics']), only: ['getStatistics', 'getAnalytics', 'getComparison', 'getPayrollStatistics']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $payrolls = $this->payrollRepository->getAll(
                $request->search,
                $request->limit,
                true
            );

            return ResponseHelper::jsonResponse(true, 'Payroll Retrieved Successfully', PayrollResource::collection($payrolls), 200);
        } catch (\Throwable $e) {
            Log::error('PayrollController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Get all payrolls with pagination
     */
    public function getAllPaginated(PayrollListRequest $request)
    {
        $validated = $request->validated();

        try {
            $payrolls = $this->payrollRepository->getAllPaginated(
                $validated['search'] ?? null,
                $validated['row_per_page'] ?? 10
            );

            return ResponseHelper::jsonResponse(true, 'Payroll Retrieved Successfully', PaginateResource::make($payrolls, PayrollResource::class), 200);
        } catch (\Throwable $e) {
            Log::error('PayrollController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Display the specified resource (summary only, without details)
     */
    public function show(string $id)
    {
        try {
            $payroll = $this->payrollRepository->getById($id);

            return ResponseHelper::jsonResponse(true, 'Payroll Retrieved Successfully', new PayrollResource($payroll), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payroll Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('PayrollController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Get payroll details with pagination (OPTIMIZED for large datasets)
     */
    public function getDetails(Request $request, string $id)
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:10|max:100',
            'page' => 'nullable|integer',
        ]);

        try {
            $perPage = $validated['per_page'] ?? 50;
            $details = $this->payrollRepository->getPayrollDetailsPaginated($id, $perPage);

            return ResponseHelper::jsonResponse(
                true,
                'Payroll Details Retrieved Successfully',
                PaginateResource::make($details, PayrollDetailResource::class),
                200
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payroll Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('PayrollController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getReconciliation(Request $request, string $id)
    {
        $validated = $request->validate([
            'severity' => 'nullable|string|in:critical,warning',
            'type' => 'nullable|string|max:100',
        ]);

        try {
            $reconciliation = $this->payrollRepository->getReconciliation($id, $validated);

            return ResponseHelper::jsonResponse(
                true,
                'Payroll Reconciliation Retrieved Successfully',
                $reconciliation,
                200
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payroll Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('PayrollController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function generateReadiness(Request $request)
    {
        $validated = $request->validate([
            'salary_month' => 'required|date_format:Y-m|before_or_equal:'.now()->format('Y-m'),
        ]);

        try {
            $readiness = $this->payrollRepository->getGenerateReadiness($validated['salary_month']);

            return ResponseHelper::jsonResponse(true, $readiness['message'], $readiness, 200);
        } catch (\Exception $e) {
            Log::warning('PayrollController domain exception: '.$e->getMessage());

            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            Log::error('PayrollController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function readinessDashboard(Request $request)
    {
        $validated = $request->validate([
            'salary_month' => 'required|date_format:Y-m',
        ]);

        try {
            $payload = $this->payrollRepository->getReadinessDashboard($validated['salary_month']);

            return ResponseHelper::jsonResponse(
                true,
                'Payroll readiness dashboard retrieved successfully.',
                $payload,
                200
            );
        } catch (\Exception $e) {
            Log::warning('PayrollController domain exception: '.$e->getMessage());

            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            Log::error('PayrollController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'salary_month' => 'required|date_format:Y-m',
        ]);

        try {
            $month = Carbon::parse($validated['salary_month'])->startOfMonth();
            $readiness = $this->payrollRepository->getGenerateReadiness($validated['salary_month']);

            if (! $readiness['can_generate']) {
                return ResponseHelper::jsonResponse(
                    false,
                    $readiness['message'],
                    $readiness,
                    422
                );
            }

            GeneratePayrollJob::dispatch($validated['salary_month'], $request->user()?->id);

            return ResponseHelper::jsonResponse(
                true,
                'Payroll generation is being processed in the background. Please check back shortly.',
                [
                    'salary_month' => $month->format('F Y'),
                    'status' => PayrollStatus::PROCESSING->value,
                ],
                200
            );
        } catch (\Exception $e) {
            Log::warning('PayrollController domain exception: '.$e->getMessage());

            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            Log::error('PayrollController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Update payroll detail (notes and final_salary)
     */
    public function updateDetail(Request $request, string $id)
    {
        $validated = $request->validate([
            'notes' => 'nullable|string',
            'final_salary' => 'nullable|integer|min:0',
            'updated_at' => 'nullable|string',
        ]);

        try {
            $payrollDetail = $this->payrollRepository->updatePayrollDetail($id, $validated, $request->user()?->id);

            return ResponseHelper::jsonResponse(true, 'Payroll Detail Updated Successfully', new PayrollDetailResource($payrollDetail), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payroll Detail Not Found', null, 404);
        } catch (PayrollStateException $e) {
            Log::warning('PayrollController domain exception: '.$e->getMessage());

            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 422);
        } catch (ConcurrentModificationException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 409);
        } catch (\Exception $e) {
            Log::warning('PayrollController domain exception: '.$e->getMessage());

            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            Log::error('PayrollController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Approve payroll before payment
     */
    public function approvePayroll(Request $request, string $id)
    {
        try {
            $payroll = $this->payrollRepository->approvePayroll($id, $request->user()?->id);

            return ResponseHelper::jsonResponse(
                true,
                'Payroll Approved Successfully',
                new PayrollResource($payroll),
                200
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payroll Not Found', null, 404);
        } catch (PayrollAlreadyPaidException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 409);
        } catch (PayrollStateException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 422);
        } catch (\Exception $e) {
            Log::warning('PayrollController domain exception: '.$e->getMessage());

            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            Log::error('PayrollController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Mark payroll as paid
     */
    public function markAsPaid(Request $request, string $id)
    {
        $validated = $request->validate([
            'payment_date' => 'required|date',
        ]);

        try {
            $payroll = $this->payrollRepository->markAsPaid($id, $validated['payment_date'], $request->user()?->id);

            return ResponseHelper::jsonResponse(true, 'Payroll Marked as Paid Successfully', new PayrollResource($payroll), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payroll Not Found', null, 404);
        } catch (PayrollAlreadyPaidException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 409);
        } catch (PayrollStateException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 422);
        } catch (PayrollReconciliationBlockedException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), ['reconciliation' => $e->getDetails()], 422);
        } catch (\Exception $e) {
            Log::warning('PayrollController domain exception: '.$e->getMessage());

            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            Log::error('PayrollController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Reopen payroll for correction
     */
    public function reopenPayroll(Request $request, string $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:10|max:500',
        ]);

        try {
            $payroll = $this->payrollRepository->reopenPayroll(
                $id,
                $validated['reason'],
                $request->user()?->id
            );

            return ResponseHelper::jsonResponse(
                true,
                'Payroll Reopened for Correction Successfully',
                new PayrollResource($payroll),
                200
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payroll Not Found', null, 404);
        } catch (\Exception $e) {
            Log::warning('PayrollController domain exception: '.$e->getMessage());

            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            Log::error('PayrollController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Resend payroll paid notifications
     */
    public function resendNotifications(Request $request, string $id)
    {
        try {
            $payroll = $this->payrollRepository->resendNotifications($id, $request->user()?->id);

            return ResponseHelper::jsonResponse(
                true,
                'Payroll notifications resent successfully',
                new PayrollResource($payroll),
                200
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payroll Not Found', null, 404);
        } catch (\Exception $e) {
            Log::warning('PayrollController domain exception: '.$e->getMessage());

            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            Log::error('PayrollController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getNotificationDeliveries(string $id)
    {
        try {
            $summary = $this->payrollRepository->getNotificationDeliverySummary($id);

            return ResponseHelper::jsonResponse(
                true,
                'Payroll notification delivery summary retrieved successfully',
                $summary,
                200
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payroll Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('PayrollController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Get payroll statistics
     */
    public function getStatistics()
    {
        try {
            $statistics = $this->payrollRepository->getStatistics();

            return ResponseHelper::jsonResponse(true, 'Payroll Statistics Retrieved Successfully', $statistics, 200);
        } catch (\Throwable $e) {
            Log::error('PayrollController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Get payroll analytics trends
     */
    public function getAnalytics(Request $request)
    {
        $validated = $request->validate([
            'months' => 'nullable|integer|min:1|max:24',
        ]);

        try {
            $analytics = $this->payrollRepository->getAnalytics((int) ($validated['months'] ?? 6));

            return ResponseHelper::jsonResponse(true, 'Payroll Analytics Retrieved Successfully', $analytics, 200);
        } catch (\Throwable $e) {
            Log::error('PayrollController::getAnalytics error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Get payroll month-over-month comparison
     */
    public function getComparison(Request $request)
    {
        $validated = $request->validate([
            'month1' => 'required|date_format:Y-m',
            'month2' => 'required|date_format:Y-m',
        ]);

        try {
            $comparison = $this->payrollRepository->getComparison($validated['month1'], $validated['month2']);

            return ResponseHelper::jsonResponse(true, 'Payroll Comparison Retrieved Successfully', $comparison, 200);
        } catch (\Throwable $e) {
            Log::error('PayrollController::getComparison error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Get specific payroll statistics
     */
    public function getPayrollStatistics(string $id)
    {
        try {
            $statistics = $this->payrollRepository->getPayrollStatistics($id);

            return ResponseHelper::jsonResponse(true, 'Payroll Statistics Retrieved Successfully', $statistics, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payroll Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('PayrollController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Export payroll to Excel
     */
    public function exportExcel(string $id)
    {
        try {
            // Verify payroll exists
            $payroll = $this->payrollRepository->findById($id);

            $this->activityLogger->log(
                $payroll->id,
                'detail_exported',
                'Payroll detail exported',
                'Detailed payroll Excel export was generated.',
                request()->user()?->id
            );

            // Generate filename
            $month = Carbon::parse($payroll->salary_month)->format('F_Y');
            $filename = "Payroll_{$month}.xlsx";

            // Export to Excel
            return Excel::download(new PayrollExport($id), $filename);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payroll Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('PayrollController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Export all payroll payslips as a ZIP of individual PDFs.
     */
    public function exportPdf(string $id)
    {
        $zipPath = null;

        try {
            $payroll = $this->payrollRepository->findByIdWithDetails($id);

            if ($payroll->payrollDetails->isEmpty()) {
                return ResponseHelper::jsonResponse(false, 'Payroll Details Not Found', null, 404);
            }

            $month = Carbon::parse($payroll->salary_month)->format('F_Y');
            $filename = "Payroll_Payslips_{$month}.zip";
            $zipPath = storage_path('app/payroll-exports/'.Str::uuid().'.zip');

            if (! is_dir(dirname($zipPath))) {
                mkdir(dirname($zipPath), 0755, true);
            }

            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return ResponseHelper::jsonResponse(false, 'Unable to create payroll PDF archive', null, 500);
            }

            foreach ($payroll->payrollDetails as $detail) {
                $pdf = $this->payslipPdfService->render($detail);
                $zip->addFromString($this->payslipFilename($detail, $month), $pdf);
            }

            $zip->close();

            $this->activityLogger->log(
                $payroll->id,
                'detail_exported',
                'Payroll payslip ZIP exported',
                'Bulk payroll payslip PDF archive was generated.',
                request()->user()?->id
            );

            return response()->download($zipPath, $filename, [
                'Content-Type' => 'application/zip',
            ])->deleteFileAfterSend(true);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payroll Not Found', null, 404);
        } catch (\Throwable $e) {
            if ($zipPath && file_exists($zipPath)) {
                @unlink($zipPath);
            }

            Log::error('PayrollController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    private function payslipFilename($detail, string $month): string
    {
        $staffMember = $detail->staffMember;
        $employeeCode = $staffMember?->code ?: 'staff-'.$detail->staff_member_id;
        $employeeName = $staffMember?->user?->name ?: $staffMember?->full_name ?: 'employee';
        $safeName = Str::of($employeeName)->ascii()->slug('_')->limit(60, '');

        return sprintf(
            '%s_%s_%s_payslip_%s.pdf',
            $month,
            Str::slug((string) $employeeCode, '_'),
            $safeName !== '' ? $safeName : 'employee',
            $detail->id
        );
    }

    /**
     * Export payroll report to Excel with filters
     */
    public function exportReport(Request $request)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,paid,all',
            'period_type' => 'required|in:monthly,yearly',
            'report_type' => 'nullable|in:summary,detail',
            'month' => 'required_if:period_type,monthly|nullable|date_format:Y-m',
            'year' => 'required_if:period_type,yearly|nullable|digits:4',
        ]);

        try {
            $rows = $this->payrollRepository->getPayrollReportRows($validated);
            $reportType = $validated['report_type'] ?? 'summary';
            foreach ($rows->pluck('payroll_id')->filter()->unique() as $payrollId) {
                $this->activityLogger->log(
                    (int) $payrollId,
                    'report_exported',
                    'Payroll report exported',
                    'Payroll summary report export was generated for this payroll period.',
                    $request->user()?->id,
                    [
                        'status' => $validated['status'],
                        'period_type' => $validated['period_type'],
                        'report_type' => $reportType,
                        'month' => $validated['month'] ?? null,
                        'year' => $validated['year'] ?? null,
                    ]
                );
            }
            $periodLabel = $validated['period_type'] === 'monthly'
                ? $validated['month']
                : $validated['year'];
            $statusLabel = ucfirst($validated['status']);
            $filename = $reportType === 'detail'
                ? "Payroll_Report_{$periodLabel}_{$statusLabel}_Detail.xlsx"
                : "Payroll_Report_{$periodLabel}_{$statusLabel}.xlsx";

            $columns = $reportType === 'detail'
                ? [
                    'period',
                    'status',
                    'employee_name',
                    'employee_code',
                    'team_name',
                    'job_title',
                    'original_salary',
                    'deduction_amount',
                    'final_salary',
                    'attended_days',
                    'sick_days',
                    'absent_days',
                    'payment_date',
                ]
                : [
                    'period',
                    'status',
                    'total_employee',
                    'total_amount',
                    'payment_date',
                    'created_at',
                ];

            $headings = $reportType === 'detail'
                ? [
                    'Periode',
                    'Status',
                    'Nama Karyawan',
                    'Kode Karyawan',
                    'Team',
                    'Jabatan',
                    'Gaji Pokok',
                    'Potongan',
                    'Gaji Bersih',
                    'Hadir',
                    'Sakit',
                    'Absen',
                    'Tanggal Pembayaran',
                ]
                : [
                    'Periode',
                    'Status',
                    'Total Karyawan',
                    'Total Gaji',
                    'Tanggal Pembayaran',
                    'Dibuat Pada',
                ];

            return Excel::download(
                new PayrollReportExport(
                    $rows,
                    $columns,
                    $headings,
                    $reportType === 'detail' ? 'Payroll Detail Report' : 'Payroll Report'
                ),
                $filename
            );
        } catch (\Throwable $e) {
            Log::error('PayrollController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getActivityLogs(string $id)
    {
        try {
            $logs = $this->payrollRepository->getActivityLogs($id);

            return ResponseHelper::jsonResponse(
                true,
                'Payroll Activity Logs Retrieved Successfully',
                PayrollActivityLogResource::collection($logs),
                200
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payroll Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('PayrollController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Get payroll readiness team summary
     */
    public function readinessTeamSummary(Request $request)
    {
        $validated = $request->validate([
            'salary_month' => 'required|date_format:Y-m',
        ]);

        try {
            $summary = $this->payrollRepository->getReadinessTeamSummary($validated['salary_month']);

            return ResponseHelper::jsonResponse(
                true,
                'Payroll readiness team summary retrieved successfully.',
                $summary,
                200
            );
        } catch (\Exception $e) {
            Log::warning('PayrollController domain exception: '.$e->getMessage());

            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            Log::error('PayrollController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Resolve a reconciliation exception
     */
    public function resolveReconciliationException(Request $request, string $id)
    {
        $validated = $request->validate([
            'staff_member_id' => 'required|integer|exists:staff_member_profiles,id',
            'exception_type' => 'required|string|max:100',
            'resolution_action' => 'required|string|in:acknowledged,waived,resolved',
            'reason' => 'required|string|max:500',
        ]);

        try {
            $resolution = $this->payrollRepository->resolveReconciliationException(
                $id,
                $validated,
                $request->user()?->id
            );

            return ResponseHelper::jsonResponse(
                true,
                'Reconciliation exception resolved successfully.',
                $resolution,
                200
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payroll Not Found', null, 404);
        } catch (\Exception $e) {
            Log::warning('PayrollController domain exception: '.$e->getMessage());

            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            Log::error('PayrollController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Get reconciliation resolutions for a payroll
     */
    public function getReconciliationResolutions(string $id)
    {
        try {
            $resolutions = $this->payrollRepository->getReconciliationResolutions($id);

            return ResponseHelper::jsonResponse(
                true,
                'Reconciliation resolutions retrieved successfully.',
                $resolutions,
                200
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payroll Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('PayrollController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }
}
