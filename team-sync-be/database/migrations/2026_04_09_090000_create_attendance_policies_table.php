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
        Schema::create('attendance_policies', function (Blueprint $table) {
            $table->id();
            $table->string('employment_type')->unique();
            $table->time('work_start_time');
            $table->time('work_end_time');
            $table->unsignedTinyInteger('work_days_per_week');
            $table->json('default_working_weekdays');
            $table->unsignedInteger('late_grace_minutes');
            $table->decimal('half_day_min_hours', 4, 2);
            $table->decimal('warning_absent_pct', 5, 2);
            $table->timestamps();

            $table->index('work_days_per_week');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_policies');
    }
};
