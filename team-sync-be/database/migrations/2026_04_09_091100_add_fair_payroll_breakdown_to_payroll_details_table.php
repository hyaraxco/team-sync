<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payroll_details', function (Blueprint $table) {
            $table->unsignedInteger('effective_working_days')->default(0)->after('final_salary');
            $table->decimal('daily_rate', 12, 2)->default(0)->after('effective_working_days');
            $table->unsignedInteger('present_days')->default(0)->after('attended_days');
            $table->unsignedInteger('late_days')->default(0)->after('present_days');
            $table->unsignedInteger('half_day_count')->default(0)->after('late_days');
            $table->unsignedInteger('paid_leave_days')->default(0)->after('half_day_count');
            $table->unsignedInteger('unpaid_leave_days')->default(0)->after('paid_leave_days');
            $table->unsignedInteger('holiday_days')->default(0)->after('unpaid_leave_days');
            $table->decimal('deduction_days', 8, 2)->default(0)->after('absent_days');
            $table->decimal('deduction_amount', 12, 2)->default(0)->after('deduction_days');
            $table->unsignedInteger('policy_mismatch_days')->default(0)->after('deduction_amount');
            $table->json('warning_flags')->nullable()->after('policy_mismatch_days');

            $table->index(['payroll_id', 'absent_days']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_details', function (Blueprint $table) {
            $table->dropIndex(['payroll_id', 'absent_days']);
            $table->dropColumn([
                'effective_working_days',
                'daily_rate',
                'present_days',
                'late_days',
                'half_day_count',
                'paid_leave_days',
                'unpaid_leave_days',
                'holiday_days',
                'deduction_days',
                'deduction_amount',
                'policy_mismatch_days',
                'warning_flags',
            ]);
        });
    }
};
