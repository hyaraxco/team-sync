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
        Schema::create('attendance_policy_mismatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendances')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->date('mismatch_date');
            $table->string('planned_work_mode')->nullable();
            $table->string('actual_work_mode')->nullable();
            $table->string('status')->default('pending_review');
            $table->foreignId('acknowledged_by')->nullable()->constrained('employee_profiles')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('employee_profiles')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['status', 'mismatch_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_policy_mismatches');
    }
};
