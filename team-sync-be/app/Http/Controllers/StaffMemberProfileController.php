<?php

namespace App\Http\Controllers;

use App\Enums\WorkLocation;
use App\Helpers\ResponseHelper;
use App\Http\Requests\StaffMemberProfileStoreRequest;
use App\Http\Requests\StaffMemberProfileUpdateRequest;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\StaffMemberProfileResource;
use App\Http\Resources\TeamMemberResource;
use App\Http\Resources\TeamResource;
use App\Interfaces\StaffMemberProfileRepositoryInterface;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class StaffMemberProfileController extends Controller implements HasMiddleware
{
    private StaffMemberProfileRepositoryInterface $staffMemberProfileRepository;

    public function __construct(StaffMemberProfileRepositoryInterface $staffMemberProfileRepository)
    {
        $this->staffMemberProfileRepository = $staffMemberProfileRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['staff-member-list|staff-member-create|staff-member-edit|staff-member-delete']), only: ['index', 'getAllPaginated', 'show', 'getStatistics']),
            new Middleware(PermissionMiddleware::using(['staff-member-create']), only: ['store', 'checkAvailability']),
            new Middleware(PermissionMiddleware::using(['staff-member-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['staff-member-delete']), only: ['destroy']),
            new Middleware(PermissionMiddleware::using(['profile-view']), only: ['getMyProfile', 'getPerformanceStatistics']),
            new Middleware(PermissionMiddleware::using(['team-view']), only: ['getMyTeam', 'getMyTeamMembers', 'getMyTeamProjects']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $employees = $this->staffMemberProfileRepository->getAll(
                $request->search,
                $request->status,
                $request->type,
                $request->work_location,
                $request->project_id,
                $request->limit,
                true
            );

            return ResponseHelper::jsonResponse(true, 'Employee Retrieved Successfully', StaffMemberProfileResource::collection($employees), 200);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('StaffMemberProfileController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getAllPaginated(Request $request): JsonResponse
    {
        $request = $request->validate([
            'search' => 'nullable|string',
            'status' => 'nullable|string',
            'type' => 'nullable|string',
            'work_location' => 'nullable|string|in:'.implode(',', array_column(WorkLocation::cases(), 'value')),
            'project_id' => 'nullable|integer',
            'row_per_page' => 'required|integer|min:1',
        ]);

        try {
            $employees = $this->staffMemberProfileRepository->getAllPaginated(
                $request['search'] ?? null,
                $request['status'] ?? null,
                $request['type'] ?? null,
                $request['work_location'] ?? null,
                $request['project_id'] ?? null,
                $request['row_per_page']
            );

            return ResponseHelper::jsonResponse(true, 'Employee Retrieved Successfully', PaginateResource::make($employees, StaffMemberProfileResource::class), 200);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('StaffMemberProfileController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StaffMemberProfileStoreRequest $request): JsonResponse
    {
        $request = $request->validated();

        try {
            $employee = $this->staffMemberProfileRepository->create($request);

            return ResponseHelper::jsonResponse(true, 'Employee Created Successfully', StaffMemberProfileResource::make($employee), 201);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('StaffMemberProfileController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Check uniqueness constraints early during multi-step create flow.
     */
    public function checkAvailability(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'email' => ['nullable', 'email'],
            'identity_number' => ['nullable', 'string', 'max:20'],
        ]);

        $errors = [];

        if (! empty($payload['email'])) {
            $emailExists = User::query()->where('email', $payload['email'])->exists();

            if ($emailExists) {
                $errors['email'] = ['The Email has already been taken.'];
            }
        }

        if (! empty($payload['identity_number'])) {
            $identityExists = StaffMemberProfile::query()
                ->where('identity_number', $payload['identity_number'])
                ->exists();

            if ($identityExists) {
                $errors['identity_number'] = ['The Identity Number has already been taken.'];
            }
        }

        if (! empty($errors)) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $errors,
            ], 422);
        }

        return ResponseHelper::jsonResponse(true, 'Values are available', [
            'email' => true,
            'identity_number' => true,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $employee = $this->staffMemberProfileRepository->getById($id);

            return ResponseHelper::jsonResponse(true, 'Employee Retrieved Successfully', StaffMemberProfileResource::make($employee), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Employee Not Found', null, 404);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('StaffMemberProfileController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StaffMemberProfileUpdateRequest $request, string $id): JsonResponse
    {
        $request = $request->validated();

        try {
            $employee = $this->staffMemberProfileRepository->update($id, $request);

            return ResponseHelper::jsonResponse(true, 'Employee Updated Successfully', StaffMemberProfileResource::make($employee), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Employee Not Found', null, 404);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('StaffMemberProfileController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $this->staffMemberProfileRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Employee Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Employee Not Found', null, 404);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('StaffMemberProfileController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Get employee statistics
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $statistics = $this->staffMemberProfileRepository->getStatistics();

            return ResponseHelper::jsonResponse(true, 'Employee statistics fetched successfully', $statistics, 200);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('StaffMemberProfileController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Get employee performance statistics
     */
    public function getPerformanceStatistics(string $id): JsonResponse
    {
        try {
            $statistics = $this->staffMemberProfileRepository->getPerformanceStatistics($id);

            return ResponseHelper::jsonResponse(true, 'Employee performance statistics fetched successfully', $statistics, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Employee Not Found', null, 404);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('StaffMemberProfileController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Get my profile (authenticated employee)
     */
    public function getMyProfile(): JsonResponse
    {
        try {
            $employee = $this->staffMemberProfileRepository->getMyProfile();

            return ResponseHelper::jsonResponse(true, 'Employee Profile Retrieved Successfully', StaffMemberProfileResource::make($employee), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Employee Profile Not Found', null, 404);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('StaffMemberProfileController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Get my team (authenticated employee)
     */
    public function getMyTeam(): JsonResponse
    {
        try {
            $team = $this->staffMemberProfileRepository->getMyTeam();

            return ResponseHelper::jsonResponse(true, 'Team Retrieved Successfully', TeamResource::make($team), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Team Not Found', null, 404);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'not assigned to any team')) {
                return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 404);
            }
            \Illuminate\Support\Facades\Log::error('StaffMemberProfileController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Get my team members (authenticated employee)
     */
    public function getMyTeamMembers(): JsonResponse
    {
        try {
            $members = $this->staffMemberProfileRepository->getMyTeamMembers();

            return ResponseHelper::jsonResponse(true, 'Team members Retrieved Successfully', TeamMemberResource::collection($members), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Team Not Found', null, 404);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'not assigned to any team')) {
                return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 404);
            }
            \Illuminate\Support\Facades\Log::error('StaffMemberProfileController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Get my team projects (authenticated employee)
     */
    public function getMyTeamProjects(): JsonResponse
    {
        try {
            $projects = $this->staffMemberProfileRepository->getMyTeamProjects();

            return ResponseHelper::jsonResponse(true, 'Team projects Retrieved Successfully', ProjectResource::collection($projects), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Team Not Found', null, 404);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'not assigned to any team')) {
                return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 404);
            }
            \Illuminate\Support\Facades\Log::error('StaffMemberProfileController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }
}
