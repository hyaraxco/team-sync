<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendancePeriod\CreateAttendancePeriodRequest;
use App\Models\AttendancePeriod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AttendancePeriodController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $periods = AttendancePeriod::orderBy('start_date', 'desc')->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $periods,
        ]);
    }

    public function store(CreateAttendancePeriodRequest $request): JsonResponse
    {
        $hasOpenPeriod = AttendancePeriod::where('status', AttendancePeriod::STATUS_OPEN)->exists();
        
        if ($hasOpenPeriod) {
            return response()->json([
                'success' => false,
                'message' => 'Masih ada periode absensi yang aktif. Tutup periode sebelumnya terlebih dahulu.',
            ], 422);
        }

        $period = AttendancePeriod::create(array_merge(
            $request->validated(),
            ['status' => AttendancePeriod::STATUS_OPEN]
        ));

        return response()->json([
            'success' => true,
            'message' => 'Periode absensi berhasil dibuat.',
            'data' => $period,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|string|in:' . AttendancePeriod::STATUS_OPEN . ',' . AttendancePeriod::STATUS_REVIEW . ',' . AttendancePeriod::STATUS_LOCKED,
        ]);

        $period = AttendancePeriod::findOrFail($id);

        // Validation for status transitions
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

        $period->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Attendance period status updated successfully.',
            'data' => $period,
        ]);
    }
}
