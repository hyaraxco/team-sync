<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\LeaveRequestBulkActionRequest;
use App\Http\Requests\LeaveRequestCalendarRequest;
use App\Http\Requests\LeaveRequestPaginatedListRequest;
use App\Http\Requests\LeaveRequestProofReviewRequest;
use App\Http\Requests\LeaveRequestProofUploadRequest;
use App\Http\Requests\LeaveRequestStoreRequest;
use App\Http\Resources\LeaveRequestResource;
use App\Http\Resources\PaginateResource;
use App\Interfaces\LeaveRequestRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Middleware\PermissionMiddleware;

class LeaveRequestController extends Controller implements HasMiddleware
{
    private LeaveRequestRepositoryInterface $leaveRequestRepository;

    public function __construct(LeaveRequestRepositoryInterface $leaveRequestRepository)
    {
        $this->leaveRequestRepository = $leaveRequestRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['leave-request-list']), only: ['index', 'getAllPaginated', 'show', 'getCalendarRequests']),
            new Middleware(PermissionMiddleware::using(['leave-request-create']), only: ['store', 'uploadProof']),
            new Middleware(PermissionMiddleware::using(['leave-request-approve']), only: ['approve', 'reject', 'bulkAction', 'reviewProof']),
            new Middleware(PermissionMiddleware::using(['leave-request-my-requests']), only: ['getMyLeaveRequests']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $leaveRequests = $this->leaveRequestRepository->getAll(
                $request->search,
                $request->limit,
                true
            );

            return ResponseHelper::jsonResponse(true, 'Leave Requests Retrieved Successfully', LeaveRequestResource::collection($leaveRequests), 200);
        } catch (AuthorizationException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 403);
        } catch (\Throwable $e) {
            Log::error('LeaveRequestController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getAllPaginated(LeaveRequestPaginatedListRequest $request)
    {
        $validated = $request->validated();

        try {
            $leaveRequests = $this->leaveRequestRepository->getAllPaginated(
                $validated['search'] ?? null,
                $validated['row_per_page']
            );

            return ResponseHelper::jsonResponse(true, 'Leave Requests Retrieved Successfully', PaginateResource::make($leaveRequests, LeaveRequestResource::class), 200);
        } catch (AuthorizationException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 403);
        } catch (\Throwable $e) {
            Log::error('LeaveRequestController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getMyLeaveRequests()
    {
        try {
            $leaveRequests = $this->leaveRequestRepository->getMyLeaveRequests();

            return ResponseHelper::jsonResponse(true, 'My Leave Requests Retrieved Successfully', LeaveRequestResource::collection($leaveRequests), 200);
        } catch (\Throwable $e) {
            Log::error('LeaveRequestController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(LeaveRequestStoreRequest $request)
    {
        $data = $request->validated();

        try {
            $leaveRequest = $this->leaveRequestRepository->store($data);

            return ResponseHelper::jsonResponse(true, 'Leave Request Created Successfully', new LeaveRequestResource($leaveRequest), 201);
        } catch (\Exception $e) {
            Log::warning('LeaveRequestController domain exception: '.$e->getMessage());

            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            Log::error('LeaveRequestController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $leaveRequest = $this->leaveRequestRepository->getById($id);

            return ResponseHelper::jsonResponse(true, 'Leave Request Retrieved Successfully', new LeaveRequestResource($leaveRequest), 200);
        } catch (AuthorizationException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 403);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Leave Request Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('LeaveRequestController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function approve(string $id)
    {
        try {
            $leaveRequest = $this->leaveRequestRepository->approve($id);

            return ResponseHelper::jsonResponse(true, 'Leave Request Approved Successfully', new LeaveRequestResource($leaveRequest), 200);
        } catch (AuthorizationException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 403);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Leave Request Not Found', null, 404);
        } catch (\Exception $e) {
            Log::warning('LeaveRequestController domain exception: '.$e->getMessage());

            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            Log::error('LeaveRequestController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function reject(string $id)
    {
        try {
            $leaveRequest = $this->leaveRequestRepository->reject($id);

            return ResponseHelper::jsonResponse(true, 'Leave Request Rejected Successfully', new LeaveRequestResource($leaveRequest), 200);
        } catch (AuthorizationException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 403);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Leave Request Not Found', null, 404);
        } catch (\Exception $e) {
            Log::warning('LeaveRequestController domain exception: '.$e->getMessage());

            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            Log::error('LeaveRequestController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function bulkAction(LeaveRequestBulkActionRequest $request)
    {
        $data = $request->validated();

        try {
            $leaveRequests = $this->leaveRequestRepository->bulkAction($data['ids'], $data['action']);

            return ResponseHelper::jsonResponse(
                true,
                'Leave Requests '.($data['action'] === 'approve' ? 'Approved' : 'Rejected').' Successfully',
                LeaveRequestResource::collection($leaveRequests),
                200
            );
        } catch (AuthorizationException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 403);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Leave Request Not Found', null, 404);
        } catch (\Exception $e) {
            Log::warning('LeaveRequestController domain exception: '.$e->getMessage());

            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            Log::error('LeaveRequestController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function uploadProof(LeaveRequestProofUploadRequest $request, string $id)
    {
        $data = $request->validated();

        try {
            $leaveRequest = $this->leaveRequestRepository->uploadProof($id, $data);

            return ResponseHelper::jsonResponse(true, 'Leave Proof Uploaded Successfully', new LeaveRequestResource($leaveRequest), 200);
        } catch (AuthorizationException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 403);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Leave Request Not Found', null, 404);
        } catch (\Exception $e) {
            Log::warning('LeaveRequestController domain exception: '.$e->getMessage());

            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            Log::error('LeaveRequestController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function reviewProof(LeaveRequestProofReviewRequest $request, string $id)
    {
        $data = $request->validated();

        try {
            $leaveRequest = $this->leaveRequestRepository->reviewProof($id, $data);

            return ResponseHelper::jsonResponse(true, 'Leave Proof Reviewed Successfully', new LeaveRequestResource($leaveRequest), 200);
        } catch (AuthorizationException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 403);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Leave Request Not Found', null, 404);
        } catch (\Exception $e) {
            Log::warning('LeaveRequestController domain exception: '.$e->getMessage());

            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            Log::error('LeaveRequestController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getCalendarRequests(LeaveRequestCalendarRequest $request)
    {
        $validated = $request->validated();

        try {
            $leaveRequests = $this->leaveRequestRepository->getCalendarData($validated['month']);

            return ResponseHelper::jsonResponse(true, 'Calendar Data Retrieved Successfully', LeaveRequestResource::collection($leaveRequests), 200);
        } catch (\Throwable $e) {
            Log::error('LeaveRequestController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }
}
