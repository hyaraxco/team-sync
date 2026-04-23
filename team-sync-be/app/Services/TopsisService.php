<?php

namespace App\Services;

/**
 * TOPSIS (Technique for Order of Preference by Similarity to Ideal Solution)
 *
 * Algoritma Multi-Criteria Decision Making untuk meranking karyawan berdasarkan
 * kinerja komprehensif dalam satu review cycle.
 *
 * Kriteria (semua Benefit — semakin besar semakin baik):
 *   C1 - avg_manager_rating       : Competency Score — weighted avg dari section competency (1-5)
 *   C2 - final_rating             : KPI Score — weighted avg dari section KPI, calibrated jika ada (1-5)
 *   C3 - avg_goal_completion      : Goal Completion % (0-100)
 *   C4 - goal_completion_ratio    : On-Time Goal Ratio — goals selesai sebelum deadline (0-1)
 *   C5 - positive_feedback_count  : jumlah feedback positif yang diterima
 *
 * Langkah-langkah:
 *   1. Bangun matriks keputusan (decision matrix)
 *   2. Normalisasi dengan metode vector: r_ij = x_ij / sqrt(sum(x_ij^2))
 *   3. Bobot normalisasi: v_ij = w_j * r_ij
 *   4. Tentukan solusi ideal positif (A+) dan negatif (A-)
 *   5. Hitung jarak Euclidean ke A+ (D+) dan A- (D-)
 *   6. Hitung closeness coefficient: C_i = D- / (D+ + D-)
 *   7. Ranking berdasarkan C_i tertinggi
 */
class TopsisService
{
    /** Nama kriteria yang digunakan */
    private const CRITERIA = [
        'avg_manager_rating',
        'final_rating',
        'avg_goal_completion',
        'goal_completion_ratio',
        'positive_feedback_count',
    ];

    /** Semua kriteria adalah Benefit (true = benefit, false = cost) */
    private const CRITERIA_TYPES = [
        'avg_manager_rating'     => true,
        'final_rating'           => true,
        'avg_goal_completion'    => true,
        'goal_completion_ratio'  => true,
        'positive_feedback_count' => true,
    ];

    /**
     * Jalankan algoritma TOPSIS lengkap.
     *
     * @param  array  $candidates   Array of ['staff_member_id', 'employee_name', 'department', 'C1'...'C5']
     * @param  array  $weights      Bobot tiap kriteria ['avg_manager_rating' => 0.35, ...]
     * @return array  Hasil ranking beserta detail kalkulasi tiap langkah
     */
    public function calculate(array $candidates, array $weights): array
    {
        if (count($candidates) < 2) {
            return $this->buildSingleResult($candidates, $weights);
        }

        // Langkah 1: Bangun matriks keputusan
        $matrix = $this->buildDecisionMatrix($candidates);

        // Langkah 2: Normalisasi vector
        $normalized = $this->normalizeMatrix($matrix);

        // Langkah 3: Bobot normalisasi
        $weighted = $this->weightedNormalize($normalized, $weights);

        // Langkah 4: Solusi ideal positif dan negatif
        [$idealPositive, $idealNegative] = $this->findIdealSolutions($weighted);

        // Langkah 5: Jarak Euclidean
        $distances = $this->calculateDistances($weighted, $idealPositive, $idealNegative);

        // Langkah 6: Closeness coefficient
        $scores = $this->calculateClosenessCoefficient($distances);

        // Langkah 7: Susun output akhir dengan ranking
        return $this->buildResult(
            $candidates,
            $matrix,
            $normalized,
            $weighted,
            $idealPositive,
            $idealNegative,
            $distances,
            $scores,
            $weights
        );
    }

    /**
     * Langkah 1: Bangun matriks keputusan dari raw data kandidat.
     * Hasil: array 2D [employee_index][criteria_key] => float
     */
    private function buildDecisionMatrix(array $candidates): array
    {
        $matrix = [];
        foreach ($candidates as $idx => $candidate) {
            foreach (self::CRITERIA as $criterion) {
                $matrix[$idx][$criterion] = (float) ($candidate[$criterion] ?? 0);
            }
        }
        return $matrix;
    }

    /**
     * Langkah 2: Normalisasi vector.
     * r_ij = x_ij / sqrt( sum_i( x_ij^2 ) )
     */
    private function normalizeMatrix(array $matrix): array
    {
        // Hitung denominator (akar kuadrat dari jumlah kuadrat) per kriteria
        $denominators = [];
        foreach (self::CRITERIA as $criterion) {
            $sumSquares = 0.0;
            foreach ($matrix as $row) {
                $sumSquares += ($row[$criterion] ** 2);
            }
            $denominators[$criterion] = $sumSquares > 0 ? sqrt($sumSquares) : 1.0;
        }

        // Normalisasi
        $normalized = [];
        foreach ($matrix as $idx => $row) {
            foreach (self::CRITERIA as $criterion) {
                $normalized[$idx][$criterion] = $row[$criterion] / $denominators[$criterion];
            }
        }

        return $normalized;
    }

    /**
     * Langkah 3: Bobot normalisasi.
     * v_ij = w_j * r_ij
     */
    private function weightedNormalize(array $normalized, array $weights): array
    {
        $weighted = [];
        foreach ($normalized as $idx => $row) {
            foreach (self::CRITERIA as $criterion) {
                $w = (float) ($weights[$criterion] ?? 0);
                $weighted[$idx][$criterion] = $w * $row[$criterion];
            }
        }
        return $weighted;
    }

    /**
     * Langkah 4: Tentukan solusi ideal positif (A+) dan negatif (A-).
     *
     * Karena semua kriteria adalah Benefit:
     *   A+ = max setiap kolom
     *   A- = min setiap kolom
     */
    private function findIdealSolutions(array $weighted): array
    {
        $idealPositive = [];
        $idealNegative = [];

        foreach (self::CRITERIA as $criterion) {
            $values = array_column($weighted, $criterion);
            $isBenefit = self::CRITERIA_TYPES[$criterion];

            $idealPositive[$criterion] = $isBenefit ? max($values) : min($values);
            $idealNegative[$criterion] = $isBenefit ? min($values) : max($values);
        }

        return [$idealPositive, $idealNegative];
    }

    /**
     * Langkah 5: Hitung jarak Euclidean ke A+ (D+) dan A- (D-).
     *
     * D+_i = sqrt( sum_j( (v_ij - A+_j)^2 ) )
     * D-_i = sqrt( sum_j( (v_ij - A-_j)^2 ) )
     */
    private function calculateDistances(array $weighted, array $idealPositive, array $idealNegative): array
    {
        $distances = [];
        foreach ($weighted as $idx => $row) {
            $sumPlus  = 0.0;
            $sumMinus = 0.0;
            foreach (self::CRITERIA as $criterion) {
                $sumPlus  += ($row[$criterion] - $idealPositive[$criterion]) ** 2;
                $sumMinus += ($row[$criterion] - $idealNegative[$criterion]) ** 2;
            }
            $distances[$idx] = [
                'distance_positive' => sqrt($sumPlus),
                'distance_negative' => sqrt($sumMinus),
            ];
        }
        return $distances;
    }

    /**
     * Langkah 6: Hitung closeness coefficient.
     * C_i = D-_i / (D+_i + D-_i)
     * Nilai C_i antara 0-1, semakin mendekati 1 semakin baik.
     */
    private function calculateClosenessCoefficient(array $distances): array
    {
        $scores = [];
        foreach ($distances as $idx => $dist) {
            $dPlus  = $dist['distance_positive'];
            $dMinus = $dist['distance_negative'];
            $total  = $dPlus + $dMinus;
            $scores[$idx] = $total > 0 ? ($dMinus / $total) : 0.0;
        }
        return $scores;
    }

    /**
     * Langkah 7: Susun output akhir.
     */
    private function buildResult(
        array $candidates,
        array $matrix,
        array $normalized,
        array $weighted,
        array $idealPositive,
        array $idealNegative,
        array $distances,
        array $scores,
        array $weights
    ): array {
        $ranking = [];
        foreach ($candidates as $idx => $candidate) {
            $ranking[] = [
                'staff_member_id'          => $candidate['staff_member_id'],
                'employee_name'        => $candidate['employee_name'],
                'department'           => $candidate['department'] ?? null,
                'raw_scores'           => $matrix[$idx],
                'normalized_scores'    => $normalized[$idx],
                'weighted_scores'      => $weighted[$idx],
                'distance_positive'    => round($distances[$idx]['distance_positive'], 6),
                'distance_negative'    => round($distances[$idx]['distance_negative'], 6),
                'closeness_coefficient' => round($scores[$idx], 6),
                'label'                => $this->getRatingLabel($scores[$idx]),
            ];
        }

        // Urutkan berdasarkan closeness_coefficient tertinggi
        usort($ranking, fn($a, $b) => $b['closeness_coefficient'] <=> $a['closeness_coefficient']);

        // Tambahkan nomor rank
        foreach ($ranking as $rank => &$item) {
            $item['rank'] = $rank + 1;
        }

        return [
            'weights'        => $weights,
            'ideal_positive' => $idealPositive,
            'ideal_negative' => $idealNegative,
            'criteria'       => self::CRITERIA,
            'criteria_types' => self::CRITERIA_TYPES,
            'ranking'        => $ranking,
            'total_candidates' => count($candidates),
        ];
    }

    /**
     * Handle edge case: hanya 1 kandidat.
     */
    private function buildSingleResult(array $candidates, array $weights): array
    {
        if (empty($candidates)) {
            return [
                'weights'          => $weights,
                'ideal_positive'   => [],
                'ideal_negative'   => [],
                'criteria'         => self::CRITERIA,
                'criteria_types'   => self::CRITERIA_TYPES,
                'ranking'          => [],
                'total_candidates' => 0,
            ];
        }

        $candidate = $candidates[0];
        $rawScores = [];
        foreach (self::CRITERIA as $criterion) {
            $rawScores[$criterion] = (float) ($candidate[$criterion] ?? 0);
        }

        return [
            'weights'          => $weights,
            'ideal_positive'   => $rawScores,
            'ideal_negative'   => array_fill_keys(self::CRITERIA, 0),
            'criteria'         => self::CRITERIA,
            'criteria_types'   => self::CRITERIA_TYPES,
            'ranking'          => [[
                'rank'                  => 1,
                'staff_member_id'           => $candidate['staff_member_id'],
                'employee_name'         => $candidate['employee_name'],
                'department'            => $candidate['department'] ?? null,
                'raw_scores'            => $rawScores,
                'normalized_scores'     => $rawScores,
                'weighted_scores'       => $rawScores,
                'distance_positive'     => 0.0,
                'distance_negative'     => 1.0,
                'closeness_coefficient' => 1.0,
                'label'                 => $this->getRatingLabel(1.0),
            ]],
            'total_candidates' => 1,
        ];
    }

    /**
     * Konversi closeness coefficient (0-1) ke label kinerja.
     */
    private function getRatingLabel(float $score): string
    {
        if ($score >= 0.80) return 'Outstanding';
        if ($score >= 0.65) return 'Exceeds Expectations';
        if ($score >= 0.50) return 'Meets Expectations';
        if ($score >= 0.35) return 'Needs Improvement';
        return 'Unsatisfactory';
    }
}
