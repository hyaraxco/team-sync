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
        Schema::table('payroll_adjustments', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_adjustments', 'approved_by')) {
                $table->foreignId('approved_by')
                    ->nullable()
                    ->after('status')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('payroll_adjustments', 'approved_at')) {
                $table->timestamp('approved_at')
                    ->nullable()
                    ->after('approved_by');
            }

            if (! Schema::hasIndex('payroll_adjustments', 'payroll_adjustments_approval_audit_index')) {
                $table->index(['approved_by', 'approved_at'], 'payroll_adjustments_approval_audit_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_adjustments', function (Blueprint $table) {
            if (Schema::hasIndex('payroll_adjustments', 'payroll_adjustments_approval_audit_index')) {
                $table->dropIndex('payroll_adjustments_approval_audit_index');
            }

            if (Schema::hasColumn('payroll_adjustments', 'approved_by')) {
                $table->dropConstrainedForeignId('approved_by');
            }

            if (Schema::hasColumn('payroll_adjustments', 'approved_at')) {
                $table->dropColumn('approved_at');
            }
        });
    }
};
