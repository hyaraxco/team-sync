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
        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->foreignId('source_period_id')->constrained('attendance_periods')->cascadeOnDelete();
            $table->foreignId('target_period_id')->constrained('attendance_periods')->cascadeOnDelete();
            $table->string('source_reference_type');
            $table->unsignedBigInteger('source_reference_id')->nullable();
            $table->string('adjustment_kind');
            $table->decimal('days_delta', 8, 2)->default(0);
            $table->decimal('amount_delta', 12, 2)->default(0);
            $table->text('reason')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['target_period_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_adjustments');
    }
};
