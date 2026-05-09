<?php

namespace App\Http\Middleware;

use App\Helpers\ResponseHelper;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectMembershipService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureProjectMembership
{
    public function __construct(
        private readonly ProjectMembershipService $membershipService
    ) {}

    public function handle(Request $request, Closure $next)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return ResponseHelper::jsonResponse(false, 'Unauthorized', null, 401);
        }

        if (! $user->hasRole('staff')) {
            return $next($request);
        }

        $projectId = $request->route('project') ?? $request->route('id');
        if (! $projectId) {
            return ResponseHelper::jsonResponse(false, 'Project ID Missing', null, 400);
        }

        $project = Project::with('teams')->find($projectId);
        if (! $project) {
            return ResponseHelper::jsonResponse(false, 'Project Not Found', null, 404);
        }

        if (! $this->membershipService->isMember($user, $project)) {
            return ResponseHelper::jsonResponse(false, 'Forbidden', null, 403);
        }

        return $next($request);
    }
}
