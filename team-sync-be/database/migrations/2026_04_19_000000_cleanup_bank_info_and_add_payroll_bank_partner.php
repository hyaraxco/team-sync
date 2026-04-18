<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cleanup migration — safe to run after 2026_04_14_000000_refactor_employee_identity_fields.
 *
 * bank_information: bank_branch & account_type were already dropped in the April 14 refactor.
 *   This migration is a no-op for columns that are already gone, handled by hasColumn checks.
 *
 * payroll_settings: April 14 refactor added payroll_bank_name + payroll_bank_code.
 *   Drop the duplicate payroll_bank_partner column if it was accidentally added.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── BankInformation: idempotent cleanup ──────────────────────────────
        Schema::table('bank_information', function (Blueprint $table) {
            if (Schema::hasColumn('bank_information', 'account_type')) {
                if (DB::getDriverName() === 'mysql') {
                    $table->dropIndex(['account_type']);
                }
                $table->dropColumn('account_type');
            }

            if (Schema::hasColumn('bank_information', 'bank_branch')) {
                $table->dropColumn('bank_branch');
            }
        });

        // ── PayrollSetting: remove duplicate payroll_bank_partner if it exists ─
        // The real columns (payroll_bank_name + payroll_bank_code) were already
        // added by 2026_04_14_000000. Drop the duplicate if it slipped in.
        Schema::table('payroll_settings', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_settings', 'payroll_bank_partner')) {
                $table->dropColumn('payroll_bank_partner');
            }
        });
    }

    public function down(): void
    {
        // Restore bank_information columns
        Schema::table('bank_information', function (Blueprint $table) {
            if (! Schema::hasColumn('bank_information', 'bank_branch')) {
                $table->string('bank_branch')->nullable()->after('account_holder_name');
            }
            if (! Schema::hasColumn('bank_information', 'account_type')) {
                $table->string('account_type')->after('bank_branch');
                if (DB::getDriverName() === 'mysql') {
                    $table->index('account_type');
                }
            }
        });
    }
};
