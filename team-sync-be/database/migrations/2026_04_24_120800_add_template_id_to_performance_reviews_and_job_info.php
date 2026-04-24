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
        Schema::table('performance_reviews', function (Blueprint $table) {
            if (!Schema::hasColumn('performance_reviews', 'review_template_id')) {
                $table->foreignId('review_template_id')->nullable()->after('reviewer_id')->constrained('performance_review_templates')->nullOnDelete();
            } elseif (!$this->hasForeignKey('performance_reviews', 'performance_reviews_review_template_id_foreign')) {
                // Column exists (from partial run) but FK was not added — add only the constraint
                $table->foreign('review_template_id')->references('id')->on('performance_review_templates')->nullOnDelete();
            }
        });

        Schema::table('job_information', function (Blueprint $table) {
            if (!Schema::hasColumn('job_information', 'review_template_id')) {
                $table->foreignId('review_template_id')->nullable()->after('job_title')->constrained('performance_review_templates')->nullOnDelete();
            } elseif (!$this->hasForeignKey('job_information', 'job_information_review_template_id_foreign')) {
                $table->foreign('review_template_id')->references('id')->on('performance_review_templates')->nullOnDelete();
            }
        });
    }

    private function hasForeignKey(string $table, string $fkName): bool
    {
        $conn = Schema::getConnection();
        $dbName = $conn->getDatabaseName();
        $count = $conn->table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $dbName)
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $fkName)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->count();
        return $count > 0;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_information', function (Blueprint $table) {
            $table->dropForeign(['review_template_id']);
            $table->dropColumn('review_template_id');
        });

        Schema::table('performance_reviews', function (Blueprint $table) {
            $table->dropForeign(['review_template_id']);
            $table->dropColumn('review_template_id');
        });
    }
};
