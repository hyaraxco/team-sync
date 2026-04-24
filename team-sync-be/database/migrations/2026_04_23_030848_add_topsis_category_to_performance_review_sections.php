<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_review_sections', function (Blueprint $table) {
            $table->enum('topsis_category', ['kpi', 'competency', 'excluded'])
                ->default('kpi')
                ->after('weight')
                ->comment('TOPSIS category: kpi sections feed C2, competency sections feed C1');
        });

        // Seed category values for existing sections
        DB::table('performance_review_sections')
            ->whereIn('name', ['Communication & Collaboration', 'Leadership & Core Values'])
            ->update(['topsis_category' => 'competency']);

        DB::table('performance_review_sections')
            ->whereIn('name', [
                'Technical Skills & Quality of Work',
                'Productivity & Time Management',
                'Initiative & Problem Solving',
            ])
            ->update(['topsis_category' => 'kpi']);
    }

    public function down(): void
    {
        Schema::table('performance_review_sections', function (Blueprint $table) {
            $table->dropColumn('topsis_category');
        });
    }
};
