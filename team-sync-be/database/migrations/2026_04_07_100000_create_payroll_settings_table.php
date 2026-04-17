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
        Schema::create('payroll_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('payday_day')->default(25);
            $table->unsignedTinyInteger('attendance_cutoff_day')->default(25);
            $table->string('working_days_mode')->default('auto_business_days');
            $table->unsignedTinyInteger('default_working_days')->default(22);
            $table->decimal('absent_deduction_rate', 5, 2)->default(1.00);
            $table->string('rounding_mode')->default('nearest');
            $table->unsignedInteger('rounding_unit')->default(1000);
            $table->text('note_template')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_settings');
    }
};
