<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Interfaces\PerformanceReviewRepositoryInterface;
use App\Services\TopsisService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Middleware\PermissionMiddleware;

class PerformanceTopsisController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(PermissionMiddleware::using('review-cycle-manage')),
        ];
    }

    /**
     * Bobot default jika HR tidak menentukan bobot sendiri.
     *
     * Sesuai PRD Section 3.2 (Kriteria Penilaian) — 5 kriteria, total = 1.0:
     *   Performance Score (30%) — gabungan kompetensi + KPI (0-100)
     *   Attendance Rate (20%)   — persentase kehadiran (0-100)
     *   Goal Completion (25%)   — penyelesaian tujuan (0-100)
     *   Feedback Score (15%)    — skor umpan balik positif (0-100)
     *   Tenure Factor (10%)     — masa kerja (0-100, cap 60 bulan)
     *
     * Keys MUST match TopsisService::CRITERIA exactly.
     * Originally implemented as 7 criteria; consolidated to 5 per PRD §3.2 alignment (2026-05-20).
     */
    private const DEFAULT_WEIGHTS = [
        'performance_score' => 0.30, // PRD Performance Score (30%)
        'attendance_rate' => 0.20,   // PRD Attendance Rate (20%)
        'goal_completion' => 0.25,   // PRD Goal Completion (25%)
        'feedback_score' => 0.15,    // PRD Feedback Score (15%)
        'tenure_factor' => 0.10,     // PRD Tenure Factor (10%)
    ];

    public function __construct(
        private PerformanceReviewRepositoryInterface $reviewRepository,
        private TopsisService $topsisService
    ) {}

    /**
     * Hitung dan kembalikan ranking TOPSIS karyawan dalam satu review cycle.
     *
     * GET /api/v1/performance/cycles/{id}/topsis-ranking
     *
     * Query params (optional) — Bobot PRD Section 3.2 (total = 1.0):
     *   - w_performance_score : float (0-1) — Performance Score (default: 0.30)
     *   - w_attendance_rate   : float (0-1) — Attendance Rate (default: 0.20)
     *   - w_goal_completion   : float (0-1) — Goal Completion (default: 0.25)
     *   - w_feedback_score    : float (0-1) — Feedback Score (default: 0.15)
     *   - w_tenure_factor     : float (0-1) — Tenure Factor (default: 0.10)
     *
     * Total bobot harus = 1.0 (jika tidak, akan dinormalisasi otomatis).
     * Nilai negatif akan di-clamp ke 0.0 sebelum normalisasi.
     */
    public function ranking(Request $request, int $id): JsonResponse
    {
        try {
            // 1. Ambil cycle — 404 jika tidak ada
            $cycle = $this->reviewRepository->getCycleById($id);

            // 2. Bangun bobot dari request atau gunakan default
            $weights = $this->resolveWeights($request);

            // 3. Ambil data skor karyawan dari DB
            $candidates = $this->reviewRepository->getEmployeeScoresForCycle($id);

            if (empty($candidates)) {
                return ResponseHelper::jsonResponse(
                    false,
                    'Tidak ada data review yang completed dalam cycle ini. TOPSIS membutuhkan minimal 1 review berstatus completed.',
                    [
                        'cycle_id' => $id,
                        'cycle_name' => $cycle->name,
                        'cycle_status' => $cycle->status,
                        'total_completed' => 0,
                        'ranking' => [],
                    ],
                    422
                );
            }

            // 4. Jalankan algoritma TOPSIS
            $result = $this->topsisService->calculate($candidates, $weights);

            // 5. Susun response
            return ResponseHelper::jsonResponse(true, 'TOPSIS ranking berhasil dihitung', [
                'cycle_id' => $id,
                'cycle_name' => $cycle->name,
                'cycle_type' => $cycle->cycle_type,
                'cycle_status' => $cycle->status,
                'review_period' => [
                    'start' => $cycle->review_period_start,
                    'end' => $cycle->review_period_end,
                ],
                ...$result,
            ]);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Review cycle tidak ditemukan', null, 404);
        } catch (\Throwable $e) {
            Log::error('PerformanceTopsisController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Ambil bobot dari query params, fallback ke default.
     * Normalisasi otomatis jika total != 1.
     * Nilai negatif di-clamp ke 0.0 (mencegah inversi kontribusi kriteria).
     */
    private function resolveWeights(Request $request): array
    {
        $keys = [
            'performance_score' => 'w_performance_score',
            'attendance_rate' => 'w_attendance_rate',
            'goal_completion' => 'w_goal_completion',
            'feedback_score' => 'w_feedback_score',
            'tenure_factor' => 'w_tenure_factor',
        ];

        $hasCustomWeights = collect($keys)->some(fn ($param) => $request->has($param));

        if (! $hasCustomWeights) {
            return self::DEFAULT_WEIGHTS;
        }

        $weights = [];
        foreach ($keys as $criterion => $param) {
            $raw = (float) $request->input($param, self::DEFAULT_WEIGHTS[$criterion]);
            $weights[$criterion] = max(0.0, $raw);

            if ($raw < 0) {
                Log::warning('TOPSIS weight clamped to zero', [
                    'criterion' => $criterion,
                    'raw_value' => $raw,
                    'user_id' => auth()->id(),
                    'cycle_id' => $request->route('id'),
                ]);
            }
        }

        // Normalisasi agar total = 1.0
        $total = array_sum($weights);
        if ($total > 0 && abs($total - 1.0) > 0.001) {
            foreach ($weights as &$w) {
                $w = round($w / $total, 6);
            }
        }

        return $weights;
    }
}
