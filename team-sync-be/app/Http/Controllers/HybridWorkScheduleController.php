<?php

namespace App\Http\Controllers;

use App\Models\HybridWorkSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class HybridWorkScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $schedules = HybridWorkSchedule::paginate($request->get('per_page', 15));

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

        $schedule = HybridWorkSchedule::where('staff_member_id', $profile->id)->first();

        return response()->json([
            'success' => true,
            'data' => $schedule,
        ]);
    }
}
