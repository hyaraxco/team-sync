<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\ApproveOvertimeRequest;
use App\Http\Requests\RejectOvertimeRequest;
use App\Http\Requests\StoreOvertimeRequest;
use App\Http\Resources\OvertimeRecordResource;
use App\Http\Resources\PaginateResource;
use App\Services\OvertimeService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Middleware\PermissionMiddleware;

class OvertimeController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly OvertimeService $overtimeService
    ) {}

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['overtime-list']), only: ['index', 'show', 'getOvertimeSummary']),
            new Middleware(PermissionMiddleware::using(['overtime-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['overtime-approve']), only: ['approve', 'reject']),
        ];
    }

    /**
     * List overtime records with optional filtering.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $records = $this->overtimeService->getAllPaginated(
                $request->query('status'),
                $request->query('staff_member_id') ? (int) $request->query('staff_member_id') : null,
                $request->query('overtime_type'),
                $request->query('date_from'),
                $request->query('date_to'),
                (int) $request->get('per_page', 15)
            );

            return ResponseHelper::jsonResponse(
                true,
                'Overtime Records Retrieved Successfully',
                PaginateResource::make($records, OvertimeRecordResource::class),
                200
            );
        } catch (\Throwable $e) {
            Log::error('OvertimeController@index Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Create a new overtime record.
     */
    public function store(StoreOvertimeRequest $request): JsonResponse
    {
        try {
            $result = $this->overtimeService->create($request->validated());

            if (! $result['success']) {
                return ResponseHelper::jsonResponse(false, $result['message'], null, 422);
            }

            return ResponseHelper::jsonResponse(
                true,
                $result['message'],
                new OvertimeRecordResource($result['record']),
                201
            );
        } catch (\Throwable $e) {
            Log::error('OvertimeController@store Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Show a single overtime record.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $record = $this->overtimeService->getById((int) $id);

            return ResponseHelper::jsonResponse(
                true,
                'Overtime Record Retrieved Successfully',
                new OvertimeRecordResource($record),
                200
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Overtime Record Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('OvertimeController@show Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Approve an overtime record.
     */
    public function approve(ApproveOvertimeRequest $request, string $id): JsonResponse
    {
        try {
            $result = $this->overtimeService->approve((int) $id, $request->user());

            if (! $result['success']) {
                return ResponseHelper::jsonResponse(false, $result['message'], null, 400);
            }

            return ResponseHelper::jsonResponse(
                true,
                $result['message'],
                new OvertimeRecordResource($result['record']),
                200
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Overtime Record Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('OvertimeController@approve Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Reject an overtime record.
     */
    public function reject(RejectOvertimeRequest $request, string $id): JsonResponse
    {
        try {
            $result = $this->overtimeService->reject(
                (int) $id,
                $request->validated()['rejection_reason'],
                $request->user()
            );

            if (! $result['success']) {
                return ResponseHelper::jsonResponse(false, $result['message'], null, 400);
            }

            return ResponseHelper::jsonResponse(
                true,
                $result['message'],
                new OvertimeRecordResource($result['record']),
                200
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Overtime Record Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('OvertimeController@reject Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Get overtime records for the authenticated employee.
     */
    public function getMyOvertime(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $staffMember = $user->staffMemberProfile;

            if (! $staffMember) {
                return ResponseHelper::jsonResponse(false, 'Staff member profile not found', null, 404);
            }

            $records = $this->overtimeService->getByStaffMember(
                $staffMember->id,
                $request->query('status'),
                (int) $request->get('per_page', 15)
            );

            return ResponseHelper::jsonResponse(
                true,
                'My Overtime Records Retrieved Successfully',
                PaginateResource::make($records, OvertimeRecordResource::class),
                200
            );
        } catch (\Throwable $e) {
            Log::error('OvertimeController@getMyOvertime Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Get overtime summary statistics.
     */
    public function getOvertimeSummary(Request $request): JsonResponse
    {
        try {
            $summary = $this->overtimeService->getSummary();

            return ResponseHelper::jsonResponse(true, 'Overtime Summary Retrieved Successfully', $summary, 200);
        } catch (\Throwable $e) {
            Log::error('OvertimeController@getOvertimeSummary Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }
}
