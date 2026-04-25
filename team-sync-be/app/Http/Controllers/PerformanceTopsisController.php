<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Interfaces\PerformanceReviewRepositoryInterface;
use App\Services\TopsisService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PerformanceTopsisController extends Controller
{
    /** Bobot default jika HR tidak menentukan bobot sendiri */
    private const DEFAULT_WEIGHTS = [
        'avg_manager_rating' => 0.30,  // C1: Competency Score
        'final_rating' => 0.30,  // C2: KPI Score
        'avg_goal_completion' => 0.20,  // C3: Goal Completion %
        'goal_completion_ratio' => 0.10,  // C4: On-Time Goal Ratio
        'positive_feedback_count' => 0.10,  // C5: Positive Feedback Count
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
     *   - w_avg_manager_rating      : float (0-1) — C1: Competency Score weight
     *   - w_final_rating            : float (0-1) — C2: KPI Score weight
     *   - w_avg_goal_completion     : float (0-1) — C3: Goal Completion weight
     *   - w_goal_completion_ratio   : float (0-1) — C4: On-Time Ratio weight
     *   - w_positive_feedback_count : float (0-1) — C5: Positive Feedback weight
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
            \Illuminate\Support\Facades\Log::error('PerformanceTopsisController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
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
            'avg_manager_rating' => 'w_avg_manager_rating',
            'final_rating' => 'w_final_rating',
            'avg_goal_completion' => 'w_avg_goal_completion',
            'goal_completion_ratio' => 'w_goal_completion_ratio',
            'positive_feedback_count' => 'w_positive_feedback_count',
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
