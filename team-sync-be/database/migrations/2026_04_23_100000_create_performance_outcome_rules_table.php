<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_outcome_rules', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->decimal('min_rating', 3, 2);
            $table->decimal('max_rating', 3, 2);
            $table->decimal('bonus_months', 4, 2)->default(0);
            $table->decimal('salary_increase_pct', 5, 2)->default(0);
            $table->boolean('promotion_eligible')->default(false);
            $table->boolean('pip_required')->default(false);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['min_rating', 'max_rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_outcome_rules');
    }
};
