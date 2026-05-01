<?php

namespace App\Repositories;

use App\Interfaces\ThrPayrollRepositoryInterface;
use App\Models\ThrPayroll;
use App\Models\ThrPayrollDetail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ThrPayrollRepository implements ThrPayrollRepositoryInterface
{
    public function getAllPaginated(?int $year, ?string $status, int $perPage = 15): LengthAwarePaginator
    {
        $query = ThrPayroll::with(['creator', 'approver'])
            ->orderByDesc('year')
            ->orderByDesc('created_at');

        if ($year !== null) {
            $query->where('year', $year);
        }

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    public function getById(int $id): ThrPayroll
    {
        return ThrPayroll::with(['creator', 'approver'])
            ->findOrFail($id);
    }

    public function getByYearAndEvent(int $year, string $religionEvent): ?ThrPayroll
    {
        return ThrPayroll::where('year', $year)
            ->where('religion_event', $religionEvent)
            ->first();
    }

    public function create(array $data): ThrPayroll
    {
        $thrPayroll = ThrPayroll::create($data);

        return $thrPayroll->load(['creator']);
    }

    public function updateStatus(ThrPayroll $thrPayroll, string $status, array $extra = []): ThrPayroll
    {
        $thrPayroll->update(array_merge(['status' => $status], $extra));

        return $thrPayroll->fresh(['creator', 'approver']);
    }

    public function updateTotals(ThrPayroll $thrPayroll): ThrPayroll
    {
        $totals = ThrPayrollDetail::where('thr_payroll_id', $thrPayroll->id)
            ->selectRaw('COUNT(*) as total_employees')
            ->selectRaw('COALESCE(SUM(gross_thr_amount), 0) as total_thr_amount')
            ->selectRaw('COALESCE(SUM(pph21_amount), 0) as total_tax_amount')
            ->selectRaw('COALESCE(SUM(net_thr_amount), 0) as total_net_amount')
            ->first();

        $thrPayroll->update([
            'total_employees' => (int) $totals->total_employees,
            'total_thr_amount' => (float) $totals->total_thr_amount,
            'total_tax_amount' => (float) $totals->total_tax_amount,
            'total_net_amount' => (float) $totals->total_net_amount,
        ]);

        return $thrPayroll->fresh(['creator', 'approver']);
    }

    public function getDetails(int $thrPayrollId, int $perPage = 15): LengthAwarePaginator
    {
        return ThrPayrollDetail::with(['staffMember.user', 'staffMember.jobInformation'])
            ->where('thr_payroll_id', $thrPayrollId)
            ->orderByDesc('gross_thr_amount')
            ->paginate($perPage);
    }

    public function bulkCreateDetails(int $thrPayrollId, array $details): int
    {
        $now = now();
        $rows = array_map(function ($detail) use ($thrPayrollId, $now) {
            return array_merge($detail, [
                'thr_payroll_id' => $thrPayrollId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, $details);

        // Insert in chunks of 100
        $inserted = 0;
        foreach (array_chunk($rows, 100) as $chunk) {
            ThrPayrollDetail::insert($chunk);
            $inserted += count($chunk);
        }

        return $inserted;
    }

    public function getYearSummary(int $year): array
    {
        $thrPayrolls = ThrPayroll::where('year', $year)
            ->withCount('details')
            ->get();

        $totalAmount = $thrPayrolls->sum('total_thr_amount');
        $totalTax = $thrPayrolls->sum('total_tax_amount');
        $totalNet = $thrPayrolls->sum('total_net_amount');
        $totalEmployees = $thrPayrolls->sum('total_employees');

        $byEvent = $thrPayrolls->map(fn ($thr) => [
            'id' => $thr->id,
            'religion_event' => $thr->religion_event,
            'event_label' => $thr->event_label,
            'status' => $thr->status,
            'total_employees' => $thr->total_employees,
            'total_thr_amount' => (float) $thr->total_thr_amount,
            'total_net_amount' => (float) $thr->total_net_amount,
            'religion_holiday_date' => $thr->religion_holiday_date?->format('Y-m-d'),
            'payment_deadline' => $thr->payment_deadline?->format('Y-m-d'),
        ]);

        return [
            'year' => $year,
            'total_events' => $thrPayrolls->count(),
            'total_employees' => $totalEmployees,
            'total_thr_amount' => round($totalAmount, 2),
            'total_tax_amount' => round($totalTax, 2),
            'total_net_amount' => round($totalNet, 2),
            'by_event' => $byEvent->values()->toArray(),
        ];
    }
}
