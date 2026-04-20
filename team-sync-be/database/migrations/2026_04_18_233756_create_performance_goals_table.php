<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('goal_type', ['okr', 'kpi', 'development', 'project']);
            $table->string('category')->nullable();
            $table->string('target_value')->nullable();
            $table->string('current_value')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('weight', 5, 2)->nullable();
            $table->date('start_date');
            $table->date('due_date');
            $table->enum('status', ['not_started', 'in_progress', 'at_risk', 'completed', 'cancelled'])->default('not_started');
            $table->tinyInteger('completion_percentage')->default(0);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('employee_profiles')->nullOnDelete();
            $table->foreignId('linked_review_id')->nullable()->constrained('performance_reviews')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_goals');
    }
};
