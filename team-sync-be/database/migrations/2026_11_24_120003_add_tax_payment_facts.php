<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->date('planned_credit_date')->nullable();
        });
        Schema::table('payroll_details', function (Blueprint $table) {
            $table->decimal('taxable_adjustment_amount', 14, 2)->default(0);
        });
        Schema::table('thr_payrolls', function (Blueprint $table) {
            $table->date('income_due_date')->nullable();
            $table->date('planned_credit_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('thr_payrolls', function (Blueprint $table) {
            $table->dropColumn(['income_due_date', 'planned_credit_date']);
        });
        Schema::table('payroll_details', function (Blueprint $table) {
            $table->dropColumn('taxable_adjustment_amount');
        });
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn('planned_credit_date');
        });
    }
};
