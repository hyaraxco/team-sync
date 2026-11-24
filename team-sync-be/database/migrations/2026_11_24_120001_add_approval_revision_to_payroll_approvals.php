<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_approvals', function (Blueprint $table) {
            // Legacy rows remain unbound until synchronization archives them.
            $table->unsignedInteger('revision')->nullable();
            $table->json('policy_snapshot')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->unique(['payroll_id', 'policy_id', 'revision'], 'payroll_approvals_revision_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_approvals', function (Blueprint $table) {
            $table->dropUnique('payroll_approvals_revision_unique');
            $table->dropColumn(['revision', 'policy_snapshot', 'superseded_at']);
        });
    }
};
