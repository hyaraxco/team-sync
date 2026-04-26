<?php

namespace App\Http\Controllers;

use App\Models\HybridScheduleOverride;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class HybridScheduleOverrideController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date',
            'planned_work_mode' => 'required|string',
            'reason' => 'required|string',
        ]);

        $profile = $request->user()->staffMemberProfile;

        if (!$profile) {
            return response()->json(['message' => 'Profile not found'], 404);
        }

        $override = HybridScheduleOverride::create([
            'staff_member_id' => $profile->id,
            'date' => $request->date,
            'planned_work_mode' => $request->planned_work_mode,
            'reason' => $request->reason,
            'status' => 'pending',
            'requested_by' => $profile->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permintaan override berhasil dikirim.',
            'data' => $override,
        ], 201);
    }

    public function approve(Request $request, HybridScheduleOverride $hybridScheduleOverride): JsonResponse
    {
        if ($hybridScheduleOverride->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya permintaan dengan status pending yang dapat disetujui.',
            ], 422);
        }

        $hybridScheduleOverride->update([
            'status' => 'approved',
            'approved_by' => $request->user()->staffMemberProfile?->id,
            'approved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permintaan override disetujui.',
            'data' => $hybridScheduleOverride,
        ]);
    }

    public function reject(Request $request, HybridScheduleOverride $hybridScheduleOverride): JsonResponse
    {
        if ($hybridScheduleOverride->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya permintaan dengan status pending yang dapat ditolak.',
            ], 422);
        }

        $request->validate([
            'review_notes' => 'required|string',
        ]);

        $hybridScheduleOverride->update([
            'status' => 'rejected',
            'review_notes' => $request->review_notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permintaan override ditolak.',
            'data' => $hybridScheduleOverride,
        ]);
    }
}
