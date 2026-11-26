<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * payrolls kept UNIQUE(salary_month) from the single-tenant era, so the first
     * company to generate a month blocked every other company. Scope the key to
     * the owning company.
     */
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropUnique(['salary_month']);
            $table->unique(['company_id', 'salary_month']);
        });

        // A single-company install has unambiguous ownership; backfill it so the
        // composite key still rejects duplicates. With several companies the
        // ownership of legacy rows is unknown and stays NULL.
        if (DB::table('companies')->count() === 1) {
            DB::table('payrolls')
                ->whereNull('company_id')
                ->update(['company_id' => DB::table('companies')->value('id')]);
        }
    }

    public function down(): void
    {
        $sharedMonth = DB::table('payrolls')
            ->select('salary_month')
            ->groupBy('salary_month')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($sharedMonth) {
            throw new RuntimeException(
                'Cannot restore the per-month payroll unique key while several companies share a salary month.'
            );
        }

        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'salary_month']);
            $table->unique('salary_month');
        });
    }
};
