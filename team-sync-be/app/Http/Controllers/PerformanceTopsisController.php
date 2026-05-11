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

    /** Bobot default jika HR tidak menentukan bobot sendiri */
    private const DEFAULT_WEIGHTS = [
        'performance_score' => 0.30,
        'attendance_rate' => 0.20,
        'goal_completion' => 0.25,
        'feedback_score' => 0.15,
        'tenure_factor' => 0.10,
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
     * Query params (optional):
     *   - w_performance_score : float (0-1) — Performance Score weight
     *   - w_attendance_rate   : float (0-1) — Attendance Rate weight
     *   - w_goal_completion   : float (0-1) — Goal Completion weight
     *   - w_feedback_score    : float (0-1) — Feedback Score weight
     *   - w_tenure_factor     : float (0-1) — Tenure Factor weight
     *
     * Total bobot harus = 1.0 (jika tidak, akan dinormalisasi otomatis).
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
            $weights[$criterion] = (float) $request->get($param, self::DEFAULT_WEIGHTS[$criterion]);
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
