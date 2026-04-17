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
        Schema::create('hybrid_schedule_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->date('date');
            $table->string('planned_work_mode');
            $table->text('reason')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('requested_by')->constrained('employee_profiles')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('employee_profiles')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'date']);
            $table->index(['status', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hybrid_schedule_overrides');
    }
};
