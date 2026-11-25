<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Fix off-by-one: min_income should equal the previous bracket's max_income,
        // not max_income + 1, so progressive calculation covers every rupiah exactly once.
        DB::table('tax_brackets')->where('order', 2)->update(['min_income' => 60_000_000]);
        DB::table('tax_brackets')->where('order', 3)->update(['min_income' => 250_000_000]);
        DB::table('tax_brackets')->where('order', 4)->update(['min_income' => 500_000_000]);
        DB::table('tax_brackets')->where('order', 5)->update(['min_income' => 5_000_000_000]);
    }

    public function down(): void
    {
        DB::table('tax_brackets')->where('order', 2)->update(['min_income' => 60_000_001]);
        DB::table('tax_brackets')->where('order', 3)->update(['min_income' => 250_000_001]);
        DB::table('tax_brackets')->where('order', 4)->update(['min_income' => 500_000_001]);
        DB::table('tax_brackets')->where('order', 5)->update(['min_income' => 5_000_000_001]);
    }
};
