<?php

namespace App\Http\Controllers;

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
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Middleware\PermissionMiddleware;

class PayrollController extends Controller implements HasMiddleware
{
    private PayrollRepositoryInterface $payrollRepository;

    private PayrollActivityLogger $activityLogger;

    public function __construct(PayrollRepositoryInterface $payrollRepository, PayrollActivityLogger $activityLogger)
    {
        $this->payrollRepository = $payrollRepository;
        $this->activityLogger = $activityLogger;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['payroll-list']), only: ['index', 'getAllPaginated', 'show', 'getDetails', 'getReconciliation', 'exportExcel', 'exportReport', 'getActivityLogs']),
            new Middleware(PermissionMiddleware::using(['payroll-create']), only: ['generate', 'generateReadiness', 'readinessDashboard']),
            new Middleware(PermissionMiddleware::using(['payroll-edit']), only: ['updateDetail', 'approvePayroll']),
            new Middleware(PermissionMiddleware::using(['payroll-process']), only: ['markAsPaid', 'reopenPayroll', 'resendNotifications', 'getNotificationDeliveries']),
            new Middleware(PermissionMiddleware::using(['payroll-statistics']), only: ['getStatistics', 'getAnalytics', 'getPayrollStatistics']),
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
            \Illuminate\Support\Facades\Log::error('PayrollController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Get all payrolls with pagination
     */
    public function getAllPaginated(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string',
            'row_per_page' => 'nullable|integer',
            'page' => 'nullable|integer',
        ]);

        try {
            $payrolls = $this->payrollRepository->getAllPaginated(
                $validated['search'] ?? null,
                $validated['row_per_page'] ?? 10
            );

            return ResponseHelper::jsonResponse(true, 'Payroll Retrieved Successfully', PaginateResource::make($payrolls, PayrollResource::class), 200);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('PayrollController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
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
            \Illuminate\Support\Facades\Log::error('PayrollController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
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
            \Illuminate\Support\Facades\Log::error('PayrollController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
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
            \Illuminate\Support\Facades\Log::error('PayrollController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function generateReadiness(Request $request)
    {
        $validated = $request->validate([
            'salary_month' => 'required|date_format:Y-m',
        ]);

        try {
            $readiness = $this->payrollRepository->getGenerateReadiness($validated['salary_month']);

            return ResponseHelper::jsonResponse(true, $readiness['message'], $readiness, 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('PayrollController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
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
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('PayrollController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
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
                    'status' => 'processing',
                ],
                200
            );
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('PayrollController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
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
            'final_salary' => 'nullable|numeric|min:0',
        ]);

        try {
            $payrollDetail = $this->payrollRepository->updatePayrollDetail($id, $validated, $request->user()?->id);

            return ResponseHelper::jsonResponse(true, 'Payroll Detail Updated Successfully', new PayrollDetailResource($payrollDetail), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payroll Detail Not Found', null, 404);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('PayrollController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
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
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('PayrollController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
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
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('PayrollController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
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
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('PayrollController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
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
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('PayrollController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
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
            \Illuminate\Support\Facades\Log::error('PayrollController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
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
            \Illuminate\Support\Facades\Log::error('PayrollController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
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
        } catch (
            \Throwable $e
        ) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
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
            \Illuminate\Support\Facades\Log::error('PayrollController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
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
            $payroll = Payroll::findOrFail($id);

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
            \Illuminate\Support\Facades\Log::error('PayrollController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
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
            \Illuminate\Support\Facades\Log::error('PayrollController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
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
            \Illuminate\Support\Facades\Log::error('PayrollController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }
}
