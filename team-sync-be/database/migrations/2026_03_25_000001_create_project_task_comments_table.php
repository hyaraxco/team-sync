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
        Schema::create('project_task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_task_id')->constrained('project_tasks')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('employee_profiles')->onDelete('cascade');
            $table->text('comment');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_task_id', 'created_at']);
            $table->index('employee_id');
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_task_comments');
    }
};
