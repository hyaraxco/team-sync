<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->foreignId('given_by')->constrained('employee_profiles')->cascadeOnDelete();
            $table->enum('feedback_type', ['positive', 'constructive', 'general']);
            $table->string('category')->nullable();
            $table->text('content');
            $table->boolean('is_private')->default(false);
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('linked_goal_id')->nullable()->constrained('performance_goals')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_feedback');
    }
};
