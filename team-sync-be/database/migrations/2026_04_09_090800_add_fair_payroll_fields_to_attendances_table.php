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
        Schema::table('attendances', function (Blueprint $table) {
            $table->unsignedInteger('worked_minutes')->nullable()->after('check_out');
            $table->string('actual_work_mode')->nullable()->after('worked_minutes');
            $table->boolean('policy_mismatch_flag')->default(false)->after('actual_work_mode');
            $table->foreignId('attendance_period_id')->nullable()->after('date')->constrained('attendance_periods')->nullOnDelete();

            $table->index(['attendance_period_id', 'status']);
            $table->index(['employee_id', 'attendance_period_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex(['attendance_period_id', 'status']);
            $table->dropIndex(['employee_id', 'attendance_period_id']);
            $table->dropConstrainedForeignId('attendance_period_id');
            $table->dropColumn([
                'worked_minutes',
                'actual_work_mode',
                'policy_mismatch_flag',
            ]);
        });
    }
};
