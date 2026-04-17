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
        if (Schema::hasTable('payroll_setting_versions')) {
            return;
        }

        Schema::create('payroll_setting_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_setting_id')->constrained('payroll_settings')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->unsignedTinyInteger('payday_day');
            $table->unsignedTinyInteger('attendance_cutoff_day');
            $table->string('working_days_mode');
            $table->unsignedTinyInteger('default_working_days');
            $table->decimal('absent_deduction_rate', 5, 2);
            $table->string('rounding_mode');
            $table->unsignedInteger('rounding_unit');
            $table->text('note_template');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('effective_at');
            $table->timestamps();

            // Keep index name below MySQL's 64-char identifier limit.
            $table->unique(['payroll_setting_id', 'version_number'], 'psv_setting_version_unique');
            $table->index('effective_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_setting_versions');
    }
};
