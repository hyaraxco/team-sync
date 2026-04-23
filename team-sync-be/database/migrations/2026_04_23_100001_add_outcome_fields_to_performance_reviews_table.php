<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_reviews', function (Blueprint $table) {
            $table->foreignId('outcome_rule_id')->nullable()->after('acknowledged_by_employee_at')
                ->constrained('performance_outcome_rules')->nullOnDelete();
            $table->decimal('bonus_months', 4, 2)->nullable()->after('outcome_rule_id');
            $table->decimal('salary_increase_pct', 5, 2)->nullable()->after('bonus_months');
            $table->boolean('promotion_eligible')->nullable()->after('salary_increase_pct');
            $table->boolean('pip_required')->nullable()->after('promotion_eligible');
            $table->timestamp('outcome_applied_at')->nullable()->after('pip_required');
        });
    }

    public function down(): void
    {
        Schema::table('performance_reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('outcome_rule_id');
            $table->dropColumn([
                'bonus_months',
                'salary_increase_pct',
                'promotion_eligible',
                'pip_required',
                'outcome_applied_at',
            ]);
        });
    }
};
