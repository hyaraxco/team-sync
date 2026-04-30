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
        Schema::create('payroll_reconciliation_resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_member_id')->constrained('staff_member_profiles')->cascadeOnDelete();
            $table->foreignId('resolved_by')->constrained('users');
            $table->string('exception_type');
            $table->enum('resolution_action', ['acknowledged', 'resolved', 'waived']);
            $table->text('reason');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['payroll_id', 'staff_member_id', 'exception_type'], 'prr_payroll_staff_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_reconciliation_resolutions');
    }
};
