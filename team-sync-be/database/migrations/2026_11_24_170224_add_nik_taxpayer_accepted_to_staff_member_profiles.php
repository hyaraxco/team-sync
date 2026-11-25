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
        // NIK taxpayer acceptance columns (PENG-6/PJ.09/2024)
        Schema::table('staff_member_profiles', function (Blueprint $table) {
            $table->string('nik_taxpayer_accepted_hash', 64)->nullable()->after('npwp');
            $table->timestamp('nik_taxpayer_accepted_at')->nullable()->after('nik_taxpayer_accepted_hash');
            $table->foreignId('nik_taxpayer_accepted_by')->nullable()->after('nik_taxpayer_accepted_at')
                ->constrained('users')->nullOnDelete();
        });

        // Rename has_npwp → has_taxpayer_id (the column snapshots taxpayer identity status, not NPWP alone)
        Schema::table('thr_payroll_details', function (Blueprint $table) {
            $table->renameColumn('has_npwp', 'has_taxpayer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('thr_payroll_details', function (Blueprint $table) {
            $table->renameColumn('has_taxpayer_id', 'has_npwp');
        });

        Schema::table('staff_member_profiles', function (Blueprint $table) {
            $table->dropForeign(['nik_taxpayer_accepted_by']);
            $table->dropColumn(['nik_taxpayer_accepted_hash', 'nik_taxpayer_accepted_at', 'nik_taxpayer_accepted_by']);
        });
    }
};
