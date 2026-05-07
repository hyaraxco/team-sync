<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\Meeting\MeetingStoreRequest;
use App\Http\Resources\MeetingResource;
use App\Http\Resources\PaginateResource;
use App\Services\MeetingService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Middleware\PermissionMiddleware;

class MeetingController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly MeetingService $meetingService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware(PermissionMiddleware::using(['meeting-list']), only: ['index', 'getAllPaginated', 'show']),
            new Middleware(PermissionMiddleware::using(['meeting-menu']), only: ['getUpcoming']),
            new Middleware(PermissionMiddleware::using(['meeting-create']), only: ['store']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $meetings = $this->meetingService->getAllPaginated(
                $request->query('search'),
                $request->query('department'),
                (int) ($request->query('row_per_page', 10)),
                $this->getTeamIdsForScope()
            );

            return ResponseHelper::jsonResponse(
                true,
                'Meetings Retrieved Successfully',
                PaginateResource::make($meetings, MeetingResource::class),
                200
            );
        } catch (\Throwable $e) {
            Log::error('MeetingController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getAllPaginated(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string'],
            'department' => ['nullable', 'string'],
            'row_per_page' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $meetings = $this->meetingService->getAllPaginated(
                $validated['search'] ?? null,
                $validated['department'] ?? null,
                $validated['row_per_page'],
                $this->getTeamIdsForScope()
            );

            return ResponseHelper::jsonResponse(
                true,
                'Meetings Retrieved Successfully',
                PaginateResource::make($meetings, MeetingResource::class),
                200
            );
        } catch (\Throwable $e) {
            Log::error('MeetingController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getUpcoming(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $meetings = $this->meetingService->getUpcoming(
                $validated['limit'] ?? 10,
                $this->getTeamIdsForScope()
            );

            return ResponseHelper::jsonResponse(true, 'Upcoming Meetings Retrieved Successfully', MeetingResource::collection($meetings), 200);
        } catch (\Throwable $e) {
            Log::error('MeetingController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $meeting = $this->meetingService->getById($id);

            // Staff can only view meetings targeted at their teams
            $user = Auth::user();
            $isReviewer = $user->hasRole('manager') || $user->hasRole('hr');

            if (! $isReviewer) {
                $teamIds = $this->getTeamIdsForScope();
                if ($teamIds !== null) {
                    $meetingTeamIds = $meeting->teams->pluck('id')->toArray();
                    if (empty(array_intersect($meetingTeamIds, $teamIds))) {
                        return ResponseHelper::jsonResponse(false, 'You do not have access to this meeting.', null, 403);
                    }
                }
            }

            return ResponseHelper::jsonResponse(true, 'Meeting Retrieved Successfully', new MeetingResource($meeting), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Meeting Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('MeetingController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function store(MeetingStoreRequest $request): JsonResponse
    {
        try {
            $meeting = $this->meetingService->create($request->validated(), $request->user());

            return ResponseHelper::jsonResponse(true, 'Meeting Created Successfully', new MeetingResource($meeting), 201);
        } catch (\Throwable $e) {
            Log::error('MeetingController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Get team IDs for scope filtering.
     * Returns null for manager/HR (no filtering), array of team IDs for staff.
     */
    private function getTeamIdsForScope(): ?array
    {
        $user = Auth::user();
        $isReviewer = $user->hasRole('manager') || $user->hasRole('hr');

        if ($isReviewer) {
            return null; // No scope restriction
        }

        $profile = $user->staffMemberProfile;
        if (! $profile) {
            return [];
        }

        $jobInfoTeamId = $profile->jobInformation?->team_id;
        $memberTeamIds = $profile->teamMembers()
            ->whereNull('left_at')
            ->pluck('team_id')
            ->toArray();

        return array_unique(array_filter(array_merge(
            $jobInfoTeamId ? [$jobInfoTeamId] : [],
            $memberTeamIds
        )));
    }
}
