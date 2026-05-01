<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thr_payroll_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thr_payroll_id')->constrained('thr_payrolls')->cascadeOnDelete();
            $table->foreignId('staff_member_id')->constrained('staff_member_profiles')->cascadeOnDelete();
            $table->string('religion'); // Employee's religion at time of generation
            $table->decimal('monthly_salary', 12, 2); // Base salary snapshot
            $table->date('join_date'); // Employment start date snapshot
            $table->unsignedSmallInteger('tenure_months'); // Calculated tenure in months
            $table->decimal('proration_factor', 5, 4); // 1.0000 for >= 12 months, else months/12
            $table->decimal('gross_thr_amount', 12, 2); // Before tax
            $table->decimal('pph21_amount', 12, 2)->default(0); // Tax on THR
            $table->decimal('net_thr_amount', 12, 2); // After tax
            $table->string('ptkp_status')->nullable(); // Tax status snapshot
            $table->boolean('has_npwp')->default(false); // NPWP status snapshot
            $table->json('tax_calculation_meta')->nullable(); // Full tax breakdown
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['thr_payroll_id', 'staff_member_id'], 'thr_detail_unique');
            $table->index('staff_member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thr_payroll_details');
    }
};
