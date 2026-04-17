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
        Schema::table('payrolls', function (Blueprint $table) {
            $table->foreignId('attendance_period_id')->nullable()->after('salary_month')->constrained('attendance_periods')->nullOnDelete();

            $table->index(['attendance_period_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropIndex(['attendance_period_id', 'status']);
            $table->dropConstrainedForeignId('attendance_period_id');
        });
    }
};
