<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Safe cleanup: drop payroll_bank_partner if it was added by the old version
 * of 2026_04_19_000000 migration. This is idempotent.
 *
 * Background: the 2026_04_19 migration was rewritten after it had already
 * run in some environments (adding payroll_bank_partner instead of dropping it).
 * This migration ensures the duplicate column is always removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_settings', 'payroll_bank_partner')) {
                $table->dropColumn('payroll_bank_partner');
            }
        });
    }

    public function down(): void
    {
        // No restoration needed — payroll_bank_partner was a duplicate/mistake.
    }
};
