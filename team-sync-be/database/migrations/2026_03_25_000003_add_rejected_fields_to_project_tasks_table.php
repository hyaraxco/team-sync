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
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->text('rejected_reason')->nullable()->after('status');
            $table->foreignId('rejected_by')
                ->nullable()
                ->after('rejected_reason')
                ->constrained('employee_profiles')
                ->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');

            $table->index('rejected_by');
            $table->index('rejected_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropIndex(['rejected_at']);
            $table->dropIndex(['rejected_by']);
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn(['rejected_reason', 'rejected_at']);
        });
    }
};
