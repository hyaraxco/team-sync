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
        Schema::create('performance_review_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('review_template_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('performance_review_templates')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('performance_review_sections')->cascadeOnDelete();
            $table->decimal('weight', 5, 2)->default(0.00);
            $table->timestamps();
            
            $table->unique(['template_id', 'section_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_template_sections');
        Schema::dropIfExists('performance_review_templates');
    }
};
