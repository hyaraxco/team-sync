<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_reviews', function (Blueprint $table) {
            $table->decimal('manager_recommended_rating', 3, 2)
                ->nullable()
                ->after('manager_assessment_submitted_at')
                ->comment('Manager overall recommendation before HR calibration');
        });

        // Migrate existing data: copy current final_rating to manager_recommended_rating
        // for reviews that have manager assessment submitted
        DB::table('performance_reviews')
            ->whereNotNull('manager_assessment_submitted_at')
            ->whereNotNull('final_rating')
            ->update(['manager_recommended_rating' => DB::raw('final_rating')]);
    }

    public function down(): void
    {
        Schema::table('performance_reviews', function (Blueprint $table) {
            $table->dropColumn('manager_recommended_rating');
        });
    }
};
