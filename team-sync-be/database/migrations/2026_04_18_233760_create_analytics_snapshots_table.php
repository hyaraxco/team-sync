<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_snapshots', function (Blueprint $table) {
            $table->id();
            $table->enum('metric_type', ['workforce', 'attendance', 'leave', 'payroll', 'project']);
            $table->string('metric_name');
            $table->enum('period_type', ['daily', 'weekly', 'monthly', 'quarterly', 'annual']);
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('value', 12, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('calculated_at');
            $table->timestamps();
            
            $table->index(['metric_type', 'metric_name', 'period_type', 'period_start'], 'idx_analytics_metric_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_snapshots');
    }
};
