<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('performance_review_cycles')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->enum('status', ['pending_self', 'pending_manager', 'pending_calibration', 'completed', 'cancelled'])->default('pending_self');
            $table->timestamp('self_assessment_submitted_at')->nullable();
            $table->timestamp('manager_assessment_submitted_at')->nullable();
            $table->decimal('final_rating', 3, 2)->nullable();
            $table->string('final_rating_label')->nullable();
            $table->timestamp('calibrated_at')->nullable();
            $table->foreignId('calibrated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('acknowledged_by_employee_at')->nullable();
            $table->timestamps();

            $table->unique(['cycle_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_reviews');
    }
};
