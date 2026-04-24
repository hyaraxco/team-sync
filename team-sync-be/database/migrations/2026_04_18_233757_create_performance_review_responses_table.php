<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_review_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('performance_reviews')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('performance_review_sections')->cascadeOnDelete();
            $table->tinyInteger('self_rating')->nullable()->comment('1-5 scale');
            $table->text('self_comments')->nullable();
            $table->tinyInteger('manager_rating')->nullable()->comment('1-5 scale');
            $table->text('manager_comments')->nullable();
            $table->tinyInteger('final_rating')->nullable()->comment('1-5 scale, after calibration');
            $table->timestamps();

            $table->unique(['review_id', 'section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_review_responses');
    }
};
