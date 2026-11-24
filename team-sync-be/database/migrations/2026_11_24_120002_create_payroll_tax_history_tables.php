<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_tax_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('staff_member_profiles')->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->json('profile');
            $table->json('history');
            $table->text('provenance');
            $table->timestamps();
            $table->unique(['employee_id', 'year']);
        });

        Schema::create('payroll_tax_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('staff_member_profiles')->restrictOnDelete();
            $table->string('source_type', 16);
            $table->unsignedBigInteger('source_id');
            $table->char('period', 7);
            $table->dateTime('recognized_at', 6);
            $table->string('allocation_key', 96);
            $table->json('input');
            $table->json('calculation')->nullable();
            $table->decimal('actual_withholding', 20, 0)->nullable();
            $table->boolean('active')->default(true);
            $table->dateTime('settled_at', 6)->nullable();
            $table->timestamps();
            $table->unique(['source_type', 'source_id']);
            $table->index(['employee_id', 'period', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_tax_entries');
        Schema::dropIfExists('payroll_tax_years');
    }
};
