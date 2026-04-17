<?php

namespace Database\Seeders;

use App\Models\TaxBracket;
use Illuminate\Database\Seeder;

class TaxBracketSeeder extends Seeder
{
    /**
     * PPh 21 progressive tax brackets per UU HPP (Harmonisasi Peraturan Perpajakan).
     */
    public function run(): void
    {
        $brackets = [
            ['min_income' => 0, 'max_income' => 60_000_000, 'rate' => 5.00, 'order' => 1],
            ['min_income' => 60_000_001, 'max_income' => 250_000_000, 'rate' => 15.00, 'order' => 2],
            ['min_income' => 250_000_001, 'max_income' => 500_000_000, 'rate' => 25.00, 'order' => 3],
            ['min_income' => 500_000_001, 'max_income' => 5_000_000_000, 'rate' => 30.00, 'order' => 4],
            ['min_income' => 5_000_000_001, 'max_income' => null, 'rate' => 35.00, 'order' => 5],
        ];

        foreach ($brackets as $bracket) {
            TaxBracket::updateOrCreate(
                ['order' => $bracket['order']],
                $bracket
            );
        }
    }
}
