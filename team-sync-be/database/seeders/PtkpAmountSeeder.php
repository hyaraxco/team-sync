<?php

namespace Database\Seeders;

use App\Models\PtkpAmount;
use Illuminate\Database\Seeder;

class PtkpAmountSeeder extends Seeder
{
    /**
     * PTKP (Penghasilan Tidak Kena Pajak) per PMK terbaru.
     * Format status: TK = Tidak Kawin, K = Kawin, K/I = Kawin + Penghasilan Istri Digabung
     * Angka setelah slash = jumlah tanggungan (max 3).
     */
    public function run(): void
    {
        $amounts = [
            // Tidak Kawin
            ['status' => 'TK/0', 'annual_amount' => 54_000_000],
            ['status' => 'TK/1', 'annual_amount' => 58_500_000],
            ['status' => 'TK/2', 'annual_amount' => 63_000_000],
            ['status' => 'TK/3', 'annual_amount' => 67_500_000],
            // Kawin
            ['status' => 'K/0', 'annual_amount' => 58_500_000],
            ['status' => 'K/1', 'annual_amount' => 63_000_000],
            ['status' => 'K/2', 'annual_amount' => 67_500_000],
            ['status' => 'K/3', 'annual_amount' => 72_000_000],
            // Kawin + Penghasilan Istri Digabung
            ['status' => 'K/I/0', 'annual_amount' => 112_500_000],
            ['status' => 'K/I/1', 'annual_amount' => 117_000_000],
            ['status' => 'K/I/2', 'annual_amount' => 121_500_000],
            ['status' => 'K/I/3', 'annual_amount' => 126_000_000],
        ];

        foreach ($amounts as $amount) {
            PtkpAmount::updateOrCreate(
                ['status' => $amount['status']],
                $amount
            );
        }
    }
}
