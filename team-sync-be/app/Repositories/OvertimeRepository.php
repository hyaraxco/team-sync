<?php

namespace App\Repositories;

use App\Interfaces\OvertimeRepositoryInterface;
use App\Models\JobInformation;
use App\Models\OvertimeRecord;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class OvertimeRepository implements OvertimeRepositoryInterface
{
    public function getAllPaginated(
        ?string $status,
        ?int $staffMemberId,
        ?string $overtimeType,
        ?string $dateFrom,
        ?string $dateTo,
        int $perPage = 15,
        ?string $search = null
    ): LengthAwarePaginator {
        $query = OvertimeRecord::with(['staffMember.user', 'approvedByUser'])
            ->orderByDesc('date');

        $manageableEmployeeIds = $this->getManageableEmployeeIdsForManager();
        if (is_array($manageableEmployeeIds)) {
            if (empty($manageableEmployeeIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('staff_member_id', $manageableEmployeeIds);
            }
        }

        if ($search !== null && $search !== '') {
            $query->whereHas('staffMember.user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        if ($staffMemberId !== null) {
            $query->where('staff_member_id', $staffMemberId);
        }

        if ($overtimeType !== null && $overtimeType !== '') {
            $query->where('overtime_type', $overtimeType);
        }

        if ($dateFrom !== null && $dateFrom !== '') {
            $query->whereDate('date', '>=', $dateFrom);
        }

        if ($dateTo !== null && $dateTo !== '') {
            $query->whereDate('date', '<=', $dateTo);
        }

        return $query->paginate($perPage);
    }

    public function getById(int $id): OvertimeRecord
    {
        $record = OvertimeRecord::with(['staffMember.user', 'approvedByUser', 'attendance'])
            ->findOrFail($id);

        $this->authorizeManagerScope($record);

        return $record;
    }

    public function getByStaffMember(int $staffMemberId, ?string $status, int $perPage = 15): LengthAwarePaginator
    {
        $query = OvertimeRecord::where('staff_member_id', $staffMemberId)
            ->orderByDesc('date');

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    public function create(array $data): OvertimeRecord
    {
        $record = OvertimeRecord::create($data);
        $record->load(['staffMember.user']);

        return $record;
    }

    public function approve(OvertimeRecord $record, int $approvedBy): OvertimeRecord
    {
        $this->authorizeManagerScope($record);

        $record->update([
            'status' => OvertimeRecord::STATUS_APPROVED,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);

        $record->load(['staffMember.user', 'approvedByUser']);

        return $record;
    }

    public function reject(OvertimeRecord $record, string $reason): OvertimeRecord
    {
        $this->authorizeManagerScope($record);

        $record->update([
            'status' => OvertimeRecord::STATUS_REJECTED,
            'rejection_reason' => $reason,
        ]);

        $record->load(['staffMember.user']);

        return $record;
    }

    public function getSummary(): array
    {
        $currentMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $query = OvertimeRecord::query();
        $manageableEmployeeIds = $this->getManageableEmployeeIdsForManager();
        if (is_array($manageableEmployeeIds)) {
            if (empty($manageableEmployeeIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('staff_member_id', $manageableEmployeeIds);
            }
        }

        $totalPending = (clone $query)->pending()->count();

        $approvedThisMonth = (clone $query)->approved()
            ->whereBetween('date', [$currentMonth, $endOfMonth])
            ->count();

        $totalHoursThisMonth = (float) (clone $query)->approved()
            ->whereBetween('date', [$currentMonth, $endOfMonth])
            ->sum('hours');

        $rejectedThisMonth = (clone $query)->rejected()
            ->whereBetween('date', [$currentMonth, $endOfMonth])
            ->count();

        $byType = (clone $query)->approved()
            ->whereBetween('date', [$currentMonth, $endOfMonth])
            ->selectRaw('overtime_type, COUNT(*) as count, SUM(hours) as total_hours')
            ->groupBy('overtime_type')
            ->get()
            ->keyBy('overtime_type')
            ->map(fn ($row) => [
                'count' => (int) $row->count,
                'total_hours' => round((float) $row->total_hours, 2),
            ]);

        return [
            'total_pending' => $totalPending,
            'approved_this_month' => $approvedThisMonth,
            'rejected_this_month' => $rejectedThisMonth,
            'total_hours_this_month' => round($totalHoursThisMonth, 2),
            'by_type' => $byType,
        ];
    }

    public function getWeeklyHoursForStaffMember(int $staffMemberId, string $date): float
    {
        $targetDate = Carbon::parse($date);
        $weekStart = $targetDate->copy()->startOfWeek();
        $weekEnd = $targetDate->copy()->endOfWeek();

        return (float) OvertimeRecord::where('staff_member_id', $staffMemberId)
            ->whereIn('status', [OvertimeRecord::STATUS_PENDING, OvertimeRecord::STATUS_APPROVED])
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->sum('hours');
    }

    public function getWeeklyHoursForStaffMemberLocked(int $staffMemberId, string $date): float
    {
        $targetDate = Carbon::parse($date);
        $weekStart = $targetDate->copy()->startOfWeek();
        $weekEnd = $targetDate->copy()->endOfWeek();

        return (float) OvertimeRecord::where('staff_member_id', $staffMemberId)
            ->whereIn('status', [OvertimeRecord::STATUS_PENDING, OvertimeRecord::STATUS_APPROVED])
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->lockForUpdate()
            ->sum('hours');
    }

    private function getManageableEmployeeIdsForManager(): ?array
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user || ! $user->hasRole('manager') || $user->hasRole('superadmin') || $user->hasRole('hr')) {
            return null;
        }

        $leadTeamIds = Team::where('team_lead_id', $user->id)
            ->pluck('id')
            ->toArray();

        if (empty($leadTeamIds)) {
            return [];
        }

        $fromTeamMembers = TeamMember::whereIn('team_id', $leadTeamIds)
            ->whereNull('left_at')
            ->pluck('staff_member_id')
            ->toArray();

        $fromJobInformation = JobInformation::whereIn('team_id', $leadTeamIds)
            ->pluck('staff_member_id')
            ->toArray();

        return array_values(array_unique(array_merge($fromTeamMembers, $fromJobInformation)));
    }

    private function authorizeManagerScope(OvertimeRecord $record): void
    {
        $manageableEmployeeIds = $this->getManageableEmployeeIdsForManager();
        if (! is_array($manageableEmployeeIds)) {
            return;
        }

        if (! in_array($record->staff_member_id, $manageableEmployeeIds, true)) {
            throw new AuthorizationException('You can only access overtime records from your direct reports.');
        }
    }
}
