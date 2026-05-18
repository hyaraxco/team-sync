<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendancePeriod\CreateAttendancePeriodRequest;
use App\Http\Resources\AttendancePeriodResource;
use App\Interfaces\AttendanceRepositoryInterface;
use App\Models\AttendancePeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class AttendancePeriodController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(PermissionMiddleware::using('attendance-menu')),
        ];
    }

    public function __construct(private AttendanceRepositoryInterface $repository) {}

    public function index(Request $request): JsonResponse
    {
        $periods = $this->repository->getAttendancePeriodsPaginated((int) $request->get('per_page', 15));
        $periods->setCollection($periods->getCollection()->map(
            fn (AttendancePeriod $period): array => (new AttendancePeriodResource($period))->resolve($request)
        ));

        return response()->json([
            'success' => true,
            'data' => $periods,
        ]);
    }

    public function store(CreateAttendancePeriodRequest $request): JsonResponse
    {
        $hasOpenPeriod = $this->repository->hasOpenAttendancePeriod();

        if ($hasOpenPeriod) {
            return response()->json([
                'success' => false,
                'message' => 'Masih ada periode absensi yang aktif. Tutup periode sebelumnya terlebih dahulu.',
            ], 422);
        }

        $period = $this->repository->createAttendancePeriod(array_merge(
            $request->validated(),
            ['status' => AttendancePeriod::STATUS_OPEN]
        ));

        return response()->json([
            'success' => true,
            'message' => 'Periode absensi berhasil dibuat.',
            'data' => new AttendancePeriodResource($period),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|string|in:'.AttendancePeriod::STATUS_OPEN.','.AttendancePeriod::STATUS_REVIEW.','.AttendancePeriod::STATUS_LOCKED,
        ]);

        $period = $this->repository->findAttendancePeriodOrFail($id);

        if ($period->status === AttendancePeriod::STATUS_LOCKED) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot change status of a locked period.',
            ], 422);
        }

        if ($period->status === AttendancePeriod::STATUS_OPEN && $data['status'] === AttendancePeriod::STATUS_LOCKED) {
            return response()->json([
                'success' => false,
                'message' => 'Must move to review before locking.',
            ], 422);
        }

        $updateData = ['status' => $data['status']];

        if ($data['status'] === AttendancePeriod::STATUS_LOCKED) {
            $updateData['locked_at'] = now();
        }

        $period = $this->repository->updateAttendancePeriod($id, $updateData);

        return response()->json([
            'success' => true,
            'message' => 'Attendance period status updated successfully.',
            'data' => new AttendancePeriodResource($period),
        ]);
    }
}
