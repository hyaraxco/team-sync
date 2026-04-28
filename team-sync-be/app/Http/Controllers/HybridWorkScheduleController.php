<?php

namespace App\Http\Controllers;

use App\Interfaces\HybridWorkScheduleRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class HybridWorkScheduleController extends Controller
{
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

    /**
     * Get the authenticated user's hybrid schedule override requests.
     */
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
