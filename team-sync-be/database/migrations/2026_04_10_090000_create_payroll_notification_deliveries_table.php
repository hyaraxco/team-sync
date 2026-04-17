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
        Schema::create('payroll_notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();
            $table->foreignId('payroll_detail_id')->nullable()->constrained('payroll_details')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employee_profiles')->nullOnDelete();
            $table->string('recipient_email')->nullable();
            $table->string('channel', 20)->default('mail');
            $table->string('trigger_type', 32);
            $table->string('delivery_status', 32);
            $table->text('failure_reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['payroll_id', 'created_at']);
            $table->index(['payroll_id', 'trigger_type']);
            $table->index(['payroll_id', 'delivery_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_notification_deliveries');
    }
};
