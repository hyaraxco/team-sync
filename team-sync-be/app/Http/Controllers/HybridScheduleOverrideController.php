<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\HybridScheduleOverrideListRequest;
use App\Http\Requests\HybridScheduleOverrideRejectRequest;
use App\Http\Requests\HybridScheduleOverrideStoreRequest;
use App\Http\Resources\HybridScheduleOverrideResource;
use App\Http\Resources\PaginateResource;
use App\Interfaces\HybridWorkScheduleRepositoryInterface;
use App\Models\HybridScheduleOverride;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Middleware\PermissionMiddleware;

class HybridScheduleOverrideController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(PermissionMiddleware::using(['attendance-menu']), only: ['index', 'approve', 'reject']),
        ];
    }

    public function __construct(private HybridWorkScheduleRepositoryInterface $repository) {}

    public function index(HybridScheduleOverrideListRequest $request): JsonResponse
    {
        $overrides = $this->repository->getOverridesPaginated(
            (int) ($request->validated('per_page') ?? 15),
            $request->validated('search'),
            $request->validated('status'),
            $request->validated('date_from'),
            $request->validated('date_to'),
        );

        return response()->json([
            'success' => true,
            'data'    => PaginateResource::make($overrides, HybridScheduleOverrideResource::class),
        ]);
    }

    public function store(HybridScheduleOverrideStoreRequest $request): JsonResponse
    {
        try {
            $profile = $request->user()->staffMemberProfile;

            if (! $profile) {
                return ResponseHelper::jsonResponse(false, 'Profile not found.', null, 404);
            }

            $override = HybridScheduleOverride::create([
                'staff_member_id' => $profile->id,
                'date' => $request->validated('date'),
                'planned_work_mode' => $request->validated('planned_work_mode'),
                'reason' => $request->validated('reason'),
                'status' => 'pending',
                'requested_by' => $profile->id,
            ]);

            return ResponseHelper::jsonResponse(true, 'Permintaan override berhasil dikirim.', $override, 201);
        } catch (\Throwable $e) {
            Log::error('HybridScheduleOverrideController store error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function approve(Request $request, HybridScheduleOverride $hybridScheduleOverride): JsonResponse
    {
        try {
            if ($hybridScheduleOverride->status !== 'pending') {
                return ResponseHelper::jsonResponse(false, 'Hanya permintaan dengan status pending yang dapat disetujui.', null, 422);
            }

            $hybridScheduleOverride->update([
                'status' => 'approved',
                'approved_by' => $request->user()->staffMemberProfile?->id,
                'approved_at' => now(),
            ]);

            return ResponseHelper::jsonResponse(true, 'Permintaan override disetujui.', $hybridScheduleOverride);
        } catch (\Throwable $e) {
            Log::error('HybridScheduleOverrideController approve error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function reject(HybridScheduleOverrideRejectRequest $request, HybridScheduleOverride $hybridScheduleOverride): JsonResponse
    {
        try {
            if ($hybridScheduleOverride->status !== 'pending') {
                return ResponseHelper::jsonResponse(false, 'Hanya permintaan dengan status pending yang dapat ditolak.', null, 422);
            }

            $hybridScheduleOverride->update([
                'status' => 'rejected',
                'review_notes' => $request->validated('review_notes'),
            ]);

            return ResponseHelper::jsonResponse(true, 'Permintaan override ditolak.', $hybridScheduleOverride);
        } catch (\Throwable $e) {
            Log::error('HybridScheduleOverrideController reject error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }
}
