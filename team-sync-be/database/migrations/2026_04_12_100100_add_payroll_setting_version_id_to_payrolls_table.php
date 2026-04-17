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
        if (! Schema::hasTable('payrolls') || Schema::hasColumn('payrolls', 'payroll_setting_version_id')) {
            return;
        }

        Schema::table('payrolls', function (Blueprint $table) {
            $table->foreignId('payroll_setting_version_id')
                ->nullable()
                ->after('attendance_period_id')
                ->constrained('payroll_setting_versions')
                ->nullOnDelete();

            $table->index(['payroll_setting_version_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('payrolls') || ! Schema::hasColumn('payrolls', 'payroll_setting_version_id')) {
            return;
        }

        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropIndex(['payroll_setting_version_id', 'status']);
            $table->dropConstrainedForeignId('payroll_setting_version_id');
        });
    }
};
