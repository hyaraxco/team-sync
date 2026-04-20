<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_goal_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained('performance_goals')->cascadeOnDelete();
            $table->foreignId('updated_by')->constrained('users')->cascadeOnDelete();
            $table->enum('update_type', ['progress', 'status_change', 'completion']);
            $table->string('previous_value')->nullable();
            $table->string('new_value')->nullable();
            $table->string('previous_status')->nullable();
            $table->string('new_status')->nullable();
            $table->tinyInteger('completion_percentage')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_goal_updates');
    }
};
