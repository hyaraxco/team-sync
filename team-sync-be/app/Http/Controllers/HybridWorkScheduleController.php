<?php

namespace App\Http\Controllers;

use App\Interfaces\HybridWorkScheduleRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class HybridWorkScheduleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(PermissionMiddleware::using('attendance-menu'), only: ['index']),
        ];
    }

    public function __construct(private HybridWorkScheduleRepositoryInterface $repository) {}

    public function index(Request $request): JsonResponse
    {
        $schedules = $this->repository->getSchedulesPaginated((int) $request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $schedules,
        ]);
    }

    public function mySchedule(Request $request): JsonResponse
    {
        $profile = $request->user()->staffMemberProfile;

        if (!$profile) {
            return response()->json(['message' => 'Profile not found'], 404);
        }

        $schedule = $this->repository->getScheduleByStaffMemberId((int) $profile->id);

        return response()->json([
            'success' => true,
            'data' => $schedule,
        ]);
    }

    public function myOverrides(Request $request): JsonResponse
    {
        $profile = $request->user()->staffMemberProfile;

        if (!$profile) {
            return response()->json(['success' => false, 'message' => 'Profile not found'], 404);
        }

        $overrides = $this->repository->getOverridesByStaffMemberIdPaginated(
            (int) $profile->id,
            (int) $request->get('per_page', 15)
        );

        return response()->json([
            'success' => true,
            'data' => $overrides,
        ]);
    }
}
