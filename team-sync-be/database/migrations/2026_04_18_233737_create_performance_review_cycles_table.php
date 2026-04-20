<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_review_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('cycle_type', ['quarterly', 'semi_annual', 'annual', 'probation']);
            $table->date('start_date');
            $table->date('end_date');
            $table->date('review_period_start');
            $table->date('review_period_end');
            $table->enum('status', ['draft', 'active', 'completed', 'cancelled'])->default('draft');
            $table->date('self_assessment_deadline')->nullable();
            $table->date('manager_assessment_deadline')->nullable();
            $table->date('calibration_deadline')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_review_cycles');
    }
};
