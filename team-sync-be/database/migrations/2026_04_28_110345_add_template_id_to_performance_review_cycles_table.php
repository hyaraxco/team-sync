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
        Schema::table('performance_review_cycles', function (Blueprint $table) {
            $table->foreignId('template_id')
                ->nullable()
                ->after('calibration_deadline')
                ->constrained('performance_review_templates')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('performance_review_cycles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_id');
        });
    }
};
