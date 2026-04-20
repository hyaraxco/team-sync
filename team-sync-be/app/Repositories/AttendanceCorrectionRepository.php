<?php

namespace App\Repositories;

use App\Interfaces\AttendanceCorrectionRepositoryInterface;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Services\Attendance\AttendanceClassifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\EmailService;

class AttendanceCorrectionRepository implements AttendanceCorrectionRepositoryInterface
{
    private AttendanceClassifier $attendanceClassifier;
    private EmailService $emailService;

    public function __construct(AttendanceClassifier $attendanceClassifier, EmailService $emailService)
    {
         $this->attendanceClassifier = $attendanceClassifier;
         $this->emailService = $emailService;
    }

    public function getAllPaginated(?string $search, int $rowPerPage, ?string $status = null)
    {
        return AttendanceCorrection::query()
            ->with(['employee.user', 'attendance', 'reviewer'])
            ->when($search, function ($query, $search) {
                $query->whereHas('employee.user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            })
            ->when($status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($rowPerPage);
    }

    public function getMyCorrections()
    {
        $employeeId = Auth::user()->employeeProfile->id;
        
        return AttendanceCorrection::query()
            ->with(['attendance'])
            ->where('employee_id', $employeeId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getById(string $id)
    {
        $correction = AttendanceCorrection::with(['employee.user', 'attendance', 'reviewer'])->find($id);

        if (!$correction) {
            throw new ModelNotFoundException("Attendance Correction not found.");
        }

        // Check if employee tries to read someone else's request
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if ($user->hasRole('employee') && $correction->employee_id !== $user->employeeProfile->id) {
            throw new AuthorizationException("You are not authorized to view this request.");
        }

        return $correction;
    }

    public function store(array $data)
    {
        $attendance = Attendance::with('attendancePeriod')->find($data['attendance_id']);
        
        if (!$attendance) {
            throw new ModelNotFoundException("Attendance record not found.");
        }

        if ($attendance->attendancePeriod && $attendance->attendancePeriod->status === 'locked') {
            throw new \Exception("Pengajuan koreksi tidak dapat diproses karena periode absensi untuk tanggal tersebut sudah ditutup atau dikunci oleh HR.");
        }

        $employeeId = Auth::user()->employeeProfile->id;

        if ($attendance->employee_id !== $employeeId) {
             throw new AuthorizationException("You are not authorized to request correction for this attendance.");
        }

        return DB::transaction(function () use ($data, $attendance, $employeeId) {
            $existingPending = AttendanceCorrection::where('attendance_id', $attendance->id)
                ->where('status', 'pending')
                ->first();

            if ($existingPending) {
                 throw new \Exception("You already have a pending correction request for this attendance.");
            }

            $correction = AttendanceCorrection::create([
                'attendance_id' => $attendance->id,
                'employee_id' => $employeeId,
                'original_check_in' => $attendance->check_in,
                'original_check_out' => $attendance->check_out,
                'requested_check_in' => $data['requested_check_in'] ?? null,
                'requested_check_out' => $data['requested_check_out'] ?? null,
                'reason' => $data['reason'],
                'status' => 'pending',
            ]);

            DB::afterCommit(function () use ($correction) {
                $this->emailService->sendAttendanceCorrectionCreatedNotification($correction);
            });

            return $correction;
        });
    }

    public function approve(string $id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $correction = $this->getById($id);

            if ($correction->status !== 'pending') {
                 throw new \Exception("This request has already been processed.");
            }

            $attendance = $correction->attendance;

            if ($attendance->attendancePeriod && $attendance->attendancePeriod->status === 'locked') {
                throw new \Exception("Koreksi tidak dapat disetujui karena periode absensi untuk tanggal tersebut sudah ditutup atau dikunci oleh HR.");
            }

            $correction->update([
                'status' => 'approved',
                'reviewed_by' => Auth::id(),
                'review_notes' => $data['review_notes'] ?? null,
            ]);

            // Apply changes to attendance record
            $attendanceData = [];
            if ($correction->requested_check_in) {
                 $attendanceData['check_in'] = $correction->requested_check_in;
            }
            if ($correction->requested_check_out) {
                 $attendanceData['check_out'] = $correction->requested_check_out;
            }

            if (! empty($attendanceData)) {
                $attendance->update($attendanceData);
                $attendance->refresh();
            }

            // Recalculate worked_minutes from the refreshed model
            $workedMinutes = 0;
            if ($attendance->check_in && $attendance->check_out) {
                $workedMinutes = max(0, \Carbon\Carbon::parse($attendance->check_in)
                    ->diffInMinutes(\Carbon\Carbon::parse($attendance->check_out)));
            }

            // Re-classify attendance to get the correct status
            $classification = $this->attendanceClassifier->classify($attendance->employee_id, $attendance->date);

            $attendance->update([
                 'status' => $classification['status'],
                 'worked_minutes' => $workedMinutes,
            ]);

            DB::afterCommit(function () use ($correction) {
                $this->emailService->sendAttendanceCorrectionApprovedNotification($correction);
            });

            return $correction;
        });
    }

    public function reject(string $id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $correction = $this->getById($id);

            if ($correction->status !== 'pending') {
                 throw new \Exception("This request has already been processed.");
            }

            $correction->update([
                'status' => 'rejected',
                'reviewed_by' => Auth::id(),
                'review_notes' => $data['review_notes'] ?? null,
            ]);

            DB::afterCommit(function () use ($correction) {
                $this->emailService->sendAttendanceCorrectionRejectedNotification($correction);
            });

            return $correction;
        });
    }
}
