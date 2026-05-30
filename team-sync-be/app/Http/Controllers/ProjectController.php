<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidProjectLeaderException;
use App\Helpers\ResponseHelper;
use App\Http\Middleware\EnsureProjectMembership;
use App\Http\Requests\Project\ProjectListRequest;
use App\Http\Requests\Project\UpdateProjectLeaderRequest;
use App\Http\Requests\ProjectStoreRequest;
use App\Http\Requests\ProjectUpdateRequest;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\StaffMemberProfileResource;
use App\Interfaces\ProjectRepositoryInterface;
use App\Models\Project;
use App\Services\ProjectMembershipService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Middleware\PermissionMiddleware;

class ProjectController extends Controller implements HasMiddleware
{
    private ProjectRepositoryInterface $projectRepository;

    private ProjectMembershipService $membershipService;

    public function __construct(
        ProjectRepositoryInterface $projectRepository,
        ProjectMembershipService $membershipService,
    ) {
        $this->projectRepository = $projectRepository;
        $this->membershipService = $membershipService;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['project-list|project-create|project-edit|project-delete']), only: ['index', 'getAllPaginated', 'show', 'getMembers']),
            new Middleware(PermissionMiddleware::using(['project-statistic']), only: ['getStatistics']),
            new Middleware(PermissionMiddleware::using(['project-statistic']), only: ['getSquadSummary']),
            new Middleware(PermissionMiddleware::using(['project-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['project-edit']), only: ['update', 'updateLeader', 'getEligibleLeaders']),
            new Middleware(PermissionMiddleware::using(['project-delete']), only: ['destroy']),
            new Middleware(EnsureProjectMembership::class, only: ['show', 'getSquadSummary', 'getMembers']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $projects = $this->projectRepository->getAll(
                $request->search,
                $request->status,
                $request->limit,
                true
            );

            return ResponseHelper::jsonResponse(true, 'Projects Retrieved Successfully', ProjectResource::collection($projects), 200);
        } catch (\Throwable $e) {
            Log::error('ProjectController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getAllPaginated(ProjectListRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $projects = $this->projectRepository->getAllPaginated(
                $validated['search'] ?? null,
                $validated['status'] ?? null,
                $validated['row_per_page']
            );

            return ResponseHelper::jsonResponse(true, 'Projects Retrieved Successfully', PaginateResource::make($projects, ProjectResource::class), 200);
        } catch (\Throwable $e) {
            Log::error('ProjectController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProjectStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $response = Gate::inspect('create', Project::class);
            if ($response->denied()) {
                return ResponseHelper::jsonResponse(false, $response->message(), null, 403);
            }

            $project = $this->projectRepository->create($data);

            return ResponseHelper::jsonResponse(true, 'Project Created Successfully', new ProjectResource($project), 201);
        } catch (AuthorizationException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 403);
        } catch (\Throwable $e) {
            Log::error('ProjectController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $project = $this->projectRepository->getById($id);

            return ResponseHelper::jsonResponse(true, 'Project Retrieved Successfully', new ProjectResource($project), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Project Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('ProjectController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProjectUpdateRequest $request, string $id): JsonResponse
    {
        $data = $request->validated();

        try {
            $project = $this->projectRepository->findById($id);

            $response = Gate::inspect('update', $project);
            if ($response->denied()) {
                return ResponseHelper::jsonResponse(false, $response->message(), null, 403);
            }

            $project = $this->projectRepository->update($id, $data);

            return ResponseHelper::jsonResponse(true, 'Project Updated Successfully', new ProjectResource($project), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Project Not Found', null, 404);
        } catch (AuthorizationException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 403);
        } catch (\Throwable $e) {
            Log::error('ProjectController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $project = $this->projectRepository->findById($id);

            $response = Gate::inspect('delete', $project);
            if ($response->denied()) {
                return ResponseHelper::jsonResponse(false, $response->message(), null, 403);
            }

            $this->projectRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Project Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Project Not Found', null, 404);
        } catch (AuthorizationException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 403);
        } catch (\Throwable $e) {
            Log::error('ProjectController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Get project statistics
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $response = Gate::inspect('viewStatistics', Project::class);
            if ($response->denied()) {
                return ResponseHelper::jsonResponse(false, $response->message(), null, 403);
            }

            $statistics = $this->projectRepository->getStatistics();

            return ResponseHelper::jsonResponse(true, 'Project Statistics Retrieved Successfully', $statistics, 200);
        } catch (AuthorizationException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 403);
        } catch (\Throwable $e) {
            Log::error('ProjectController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getSquadSummary(string $id): JsonResponse
    {
        try {
            $project = $this->projectRepository->findById($id);

            $response = Gate::inspect('viewSquadSummary', $project);
            if ($response->denied()) {
                return ResponseHelper::jsonResponse(false, $response->message(), null, 403);
            }

            $summary = $this->projectRepository->getSquadSummary($id);

            return ResponseHelper::jsonResponse(true, 'Project Squad Summary Retrieved Successfully', $summary, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Project Not Found', null, 404);
        } catch (AuthorizationException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 403);
        } catch (\Throwable $e) {
            Log::error('ProjectController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * List all active project members (used by task assignee picker, etc).
     */
    public function getMembers(string $id): JsonResponse
    {
        try {
            $project = $this->projectRepository->findById($id);
            $project->loadMissing('teams');

            $members = $this->membershipService->getProjectMembers($project);

            return ResponseHelper::jsonResponse(
                true,
                'Project Members Retrieved Successfully',
                StaffMemberProfileResource::collection($members),
                200
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Project Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('ProjectController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * List eligible project leader candidates with optional seniority filter.
     * Manager only — guarded by project-edit permission middleware.
     */
    public function getEligibleLeaders(Request $request, string $id): JsonResponse
    {
        try {
            $project = $this->projectRepository->findById($id);
            $project->loadMissing('teams');

            $seniority = $request->query('seniority_level');
            $result = $this->membershipService->getEligibleLeaders(
                $project,
                is_string($seniority) ? $seniority : null,
            );

            return ResponseHelper::jsonResponse(
                true,
                'Eligible Project Leaders Retrieved Successfully',
                [
                    'members' => StaffMemberProfileResource::collection($result['members'])->toArray($request),
                    'warning' => $result['warning'],
                ],
                200,
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Project Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('ProjectController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Reassign the project leader. Manager only — guarded by project-edit middleware.
     * Business validation is delegated to ProjectMembershipService::reassignLeader.
     */
    public function updateLeader(UpdateProjectLeaderRequest $request, string $id): JsonResponse
    {
        $data = $request->validated();

        try {
            $project = $this->membershipService->reassignLeader(
                (int) $id,
                (int) $data['project_leader_id'],
            );

            return ResponseHelper::jsonResponse(
                true,
                'Project Leader Updated Successfully',
                new ProjectResource($project),
                200
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Project Not Found', null, 404);
        } catch (InvalidProjectLeaderException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            Log::error('ProjectController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }
}
