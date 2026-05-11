<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\GenerateThrRequest;
use App\Http\Requests\MarkThrPaidRequest;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\ThrPayrollDetailResource;
use App\Http\Resources\ThrPayrollResource;
use App\Models\ThrPayroll;
use App\Services\ThrService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Middleware\PermissionMiddleware;

class ThrPayrollController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly ThrService $thrService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware(PermissionMiddleware::using(['thr-list']), only: ['index', 'show', 'getDetails', 'getYearSummary']),
            new Middleware(PermissionMiddleware::using(['thr-generate']), only: ['generate', 'simulate']),
            new Middleware(PermissionMiddleware::using(['thr-approve']), only: ['approve', 'reopen']),
            new Middleware(PermissionMiddleware::using(['thr-process']), only: ['markAsPaid']),
        ];
    }

    /**
     * List all THR payrolls with optional filtering.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $thrPayrolls = $this->thrService->getAllPaginated(
                $request->query('year') ? (int) $request->query('year') : null,
                $request->query('status'),
                (int) $request->get('per_page', 15)
            );

            return ResponseHelper::jsonResponse(
                true,
                'THR Payrolls Retrieved Successfully',
                PaginateResource::make($thrPayrolls, ThrPayrollResource::class),
                200
            );
        } catch (\Throwable $e) {
            Log::error('ThrPayrollController@index Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Show a single THR payroll.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $thrPayroll = $this->thrService->getById((int) $id);

            return ResponseHelper::jsonResponse(
                true,
                'THR Payroll Retrieved Successfully',
                new ThrPayrollResource($thrPayroll),
                200
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'THR Payroll Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('ThrPayrollController@show Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Get THR payroll details (per-employee breakdown).
     */
    public function getDetails(string $id, Request $request): JsonResponse
    {
        try {
            $details = $this->thrService->getDetails(
                (int) $id,
                (int) $request->get('per_page', 15)
            );

            return ResponseHelper::jsonResponse(
                true,
                'THR Payroll Details Retrieved Successfully',
                PaginateResource::make($details, ThrPayrollDetailResource::class),
                200
            );
        } catch (\Throwable $e) {
            Log::error('ThrPayrollController@getDetails Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Get year summary of all THR payrolls.
     */
    public function getYearSummary(Request $request): JsonResponse
    {
        try {
            $year = (int) ($request->query('year') ?? now()->year);
            $summary = $this->thrService->getYearSummary($year);

            return ResponseHelper::jsonResponse(true, 'THR Year Summary Retrieved Successfully', $summary, 200);
        } catch (\Throwable $e) {
            Log::error('ThrPayrollController@getYearSummary Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Simulate THR generation (preview without persisting).
     */
    public function simulate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'religion_event' => ['required', 'string', 'in:'.implode(',', array_values(ThrPayroll::RELIGION_EVENT_MAP))],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'religion_holiday_date' => ['required', 'date'],
        ]);

        try {
            $simulation = $this->thrService->simulate(
                $validated['religion_event'],
                (int) $validated['year'],
                $validated['religion_holiday_date']
            );

            return ResponseHelper::jsonResponse(true, 'THR Simulation Generated Successfully', $simulation, 200);
        } catch (\Throwable $e) {
            Log::error('ThrPayrollController@simulate Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Generate THR payroll for a religion event.
     */
    public function generate(GenerateThrRequest $request): JsonResponse
    {
        try {
            $result = $this->thrService->generate($request->validated(), $request->user());

            if (! $result['success']) {
                return ResponseHelper::jsonResponse(false, $result['message'], null, 422);
            }

            return ResponseHelper::jsonResponse(
                true,
                $result['message'],
                new ThrPayrollResource($result['thr_payroll']),
                201
            );
        } catch (\Throwable $e) {
            Log::error('ThrPayrollController@generate Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Approve a THR payroll.
     */
    public function approve(string $id, Request $request): JsonResponse
    {
        try {
            $result = $this->thrService->approve((int) $id, $request->user());

            if (! $result['success']) {
                return ResponseHelper::jsonResponse(false, $result['message'], null, 400);
            }

            return ResponseHelper::jsonResponse(
                true,
                $result['message'],
                new ThrPayrollResource($result['thr_payroll']),
                200
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'THR Payroll Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('ThrPayrollController@approve Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Mark THR as paid.
     */
    public function markAsPaid(MarkThrPaidRequest $request, string $id): JsonResponse
    {
        try {
            $result = $this->thrService->markAsPaid(
                (int) $id,
                $request->validated()['payment_date'],
                $request->user()
            );

            if (! $result['success']) {
                return ResponseHelper::jsonResponse(false, $result['message'], null, 400);
            }

            return ResponseHelper::jsonResponse(
                true,
                $result['message'],
                new ThrPayrollResource($result['thr_payroll']),
                200
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'THR Payroll Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('ThrPayrollController@markAsPaid Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Reopen an approved/paid THR payroll back to pending for correction.
     */
    public function reopen(string $id, Request $request): JsonResponse
    {
        try {
            $result = $this->thrService->reopen((int) $id, $request->user());

            if (! $result['success']) {
                return ResponseHelper::jsonResponse(false, $result['message'], null, 400);
            }

            return ResponseHelper::jsonResponse(
                true,
                $result['message'],
                new ThrPayrollResource($result['thr_payroll']),
                200
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'THR Payroll Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('ThrPayrollController@reopen Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }
}
